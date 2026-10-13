<?php
/**
 * Shared scaffolding for the Cloudflare Turnstile harnesses.
 *
 * check() with its argument order enforced, a notifier that really calls
 * attached observers (so the add-on seams are exercised), a fake database,
 * the two messageStack signatures, and an observer subclass whose call to
 * Cloudflare is scripted while everything that reads the answer runs for real.
 */

function ts_plugin_dir()
{
    static $dir = null;
    if ($dir !== null) {
        return $dir;
    }
    $found = [];
    foreach (glob(dirname(__DIR__) . '/zc_plugins/Turnstile/v*', GLOB_ONLYDIR) as $candidate) {
        if (is_file($candidate . '/manifest.php')) {
            $found[] = str_replace('\\', '/', $candidate);
        }
    }
    if ($found === []) {
        fwrite(STDERR, "cannot locate the plugin directory\n");
        exit(2);
    }
    usort($found, static function ($a, $b) {
        return version_compare(basename($a), basename($b));
    });
    return $dir = end($found);
}

function ts_repo_root()
{
    return dirname(ts_plugin_dir(), 3);
}

$GLOBALS['ts_failures'] = 0;

function check($label, $cond)
{
    if (!is_string($label) || is_string($cond)) {
        fwrite(STDERR, "\n  ABORT check() takes (label, condition) -- arguments look reversed\n");
        exit(2);
    }
    if ($cond) {
        echo "  ok    $label\n";
    } else {
        $GLOBALS['ts_failures']++;
        echo "  FAIL  $label\n";
    }
}

function section($title)
{
    echo "\n[$title]\n";
}

function ts_done($summary)
{
    echo "\n";
    if ($GLOBALS['ts_failures'] > 0) {
        echo "FAIL: {$GLOBALS['ts_failures']} check(s) failed -- $summary\n";
        exit(1);
    }
    echo "PASS: $summary\n";
    exit(0);
}

/**
 * Zen Cart's notifier: attach() records observers, notify() calls their
 * update<CamelEvent>() methods with the same by-reference parameters core
 * passes.
 */
class base
{
    public static $observers = [];
    public static $events = [];

    public function attach(&$observer, $eventIDArray)
    {
        foreach ($eventIDArray as $eventID) {
            self::$observers[$eventID][] = $observer;
        }
    }

    public function notify($eventID, $param1 = [], &$param2 = null, &$param3 = null, &$param4 = null, &$param5 = null)
    {
        self::$events[] = [$eventID, $param1];
        foreach (self::$observers[$eventID] ?? [] as $observer) {
            $method = 'update' . str_replace(' ', '', ucwords(strtolower(str_replace('_', ' ', $eventID))));
            if (method_exists($observer, $method)) {
                $observer->$method($this, $eventID, $param1, $param2, $param3, $param4, $param5);
            }
        }
    }

    public static function reset()
    {
        self::$observers = [];
        self::$events = [];
    }
}

class TsNotifier extends base
{
}

class TsResult
{
    public $fields = [];
    public $EOF = true;

    public function __construct(array $rows = [])
    {
        $this->fields = $rows[0] ?? [];
        $this->EOF = ($rows === []);
    }
}

class TsDb
{
    public $log = [];
    public $answers = [];

    public function Execute($sql)
    {
        $sql = preg_replace('/\s+/', ' ', trim($sql));
        $this->log[] = $sql;
        foreach ($this->answers as $needle => $rows) {
            if (stripos($sql, $needle) !== false) {
                return new TsResult($rows);
            }
        }
        return new TsResult([]);
    }

    public function prepare_input($s)
    {
        return addslashes((string)$s);
    }

    public function matching($needle)
    {
        return array_values(array_filter($this->log, static function ($s) use ($needle) {
            return stripos($s, $needle) !== false;
        }));
    }
}

class TsMessageStack
{
    public $messages = [];

    /** Storefront: add($class, $message, $type) */
    public function add($a, $b = '', $c = 'error')
    {
        $this->messages[] = ['class' => $a, 'message' => $b, 'type' => $c];
    }

    /** Admin: add_session($message, $type) */
    public function add_session($a, $b = 'error')
    {
        $this->messages[] = ['class' => 'admin', 'message' => $a, 'type' => $b];
    }
}

function ts_define_environment(array $settings = [])
{
    foreach (['DB_PREFIX' => 'zen_', 'TABLE_CONFIGURATION' => 'zen_configuration', 'TABLE_CONFIGURATION_GROUP' => 'zen_configuration_group', 'TABLE_TEMPLATE_SELECT' => 'zen_template_select'] as $k => $v) {
        if (!defined($k)) {
            define($k, $v);
        }
    }
    $defaults = [
        'TURNSTILE_STATUS' => 'true',
        'TURNSTILE_SITE_KEY' => '0x4AAAAAAAsiteKEY',
        'TURNSTILE_SECRET_KEY' => '0x4AAAAAAAsecretKEYdoNotLeak',
        'TURNSTILE_CREATE_ACCOUNT' => 'true',
        'TURNSTILE_CONTACT_US' => 'true',
    ];
    foreach (array_merge($defaults, $settings) as $k => $v) {
        if ($v !== null && !defined($k)) {
            define($k, $v);
        }
    }
    $define = require ts_plugin_dir() . '/catalog/includes/languages/english/extra_definitions/lang.turnstile.php';
    foreach ($define as $k => $v) {
        if (!defined($k)) {
            define($k, $v);
        }
    }
}

/**
 * Load the observer and a subclass whose siteverify call is scripted:
 * TsObserver::$replies is a queue of response bodies (null = transport
 * failure); TsObserver::$sent records each payload.
 */
function ts_load_observer()
{
    require_once ts_plugin_dir() . '/catalog/includes/classes/observers/auto.turnstile.php';
    if (!class_exists('TsObserver', false)) {
        // Declared here, once its parent exists.
        class TsObserver extends zcObserverTurnstile
        {
            public static $replies = [];
            public static $sent = [];

            protected static function post(string $payload): ?string
            {
                parse_str($payload, $fields);
                self::$sent[] = $fields;
                return array_shift(self::$replies);
            }

            public static function resetForms()
            {
                self::$formsCache = null;
            }
        }
    }
}
