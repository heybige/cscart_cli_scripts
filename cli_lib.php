<?php
    $php_value = phpversion();
    if (version_compare($php_value, '5.3.0') == -1) {
        echo 'Currently installed PHP version (' . $php_value . ') is not supported. Minimal required PHP version is  5.3.0.';
        die();
    }

    define('AREA', 'A');
    define('ACCOUNT_TYPE', 'admin');

    use Tygh\Bootstrap;
    use Tygh\Registry;
    use Tygh\Debugger;

    // Register autoloader
    $this_dir = dirname(__FILE__);
	$this_dir = "/home/eshop4golf";

    $classLoader = require($this_dir . '/app/lib/vendor/autoload.php');
    $classLoader->add('Tygh', $this_dir . '/app');

    // Prepare environment and process request vars
    list($_REQUEST, $_SERVER) = Bootstrap::initEnv($_GET, $_POST, $_SERVER, $this_dir);

    // Get config data
    $config = require(DIR_ROOT . '/config.php');

    Debugger::init();

    // Start debugger log
    Debugger::checkpoint('Before init');

    // Check if software is installed
    if ($config['db_host'] == '%DB_HOST%') {
        $product_name = (PRODUCT_EDITION == 'ULTIMATE' ? PRODUCT_NAME : 'Multi-Vendor');
        die($product_name . ' is <b>not installed</b>. Please click here to start the installation process: <a href="install/">[install]</a>');
    }

    // Load core functions
    $fn_list = array(
        'fn.database.php',
        'fn.users.php',
        'fn.catalog.php',
        'fn.cms.php',
        'fn.cart.php',
        'fn.locations.php',
        'fn.common.php',
        'fn.fs.php',
        'fn.images.php',
        'fn.init.php',
        'fn.control.php',
        'fn.search.php',
        'fn.promotions.php',
        'fn.log.php',
        'fn.companies.php',
        'fn.addons.php'
    );

    $fn_list[] = 'fn.' . strtolower(PRODUCT_EDITION) . '.php';

    foreach ($fn_list as $file) {
        require($config['dir']['functions'] . $file);
    }

    Registry::set('class_loader', $classLoader);
    Registry::set('config', $config);
    unset($config);

    // Connect to database
    if (!db_initiate(Registry::get('config.db_host'), Registry::get('config.db_user'), Registry::get('config.db_password'), Registry::get('config.db_name'))) {
        fn_error('Cannot connect to the database server');
    }

    register_shutdown_function(array('\\Tygh\\Registry', 'save'));

    // define lifetime for the cache data
    date_default_timezone_set('UTC'); // setting temporary timezone to avoid php warnings

    if (defined('API')) {
        fn_init_stack(
            array('fn_init_api')
        );
    }

    fn_init_stack(
        array('fn_init_storage'),
        array('fn_init_ua')
    );

    if (fn_allowed_for('ULTIMATE')) {
        fn_init_stack(array('fn_init_store_params_by_host', &$_REQUEST));
    }

    fn_init_stack(
        array(array('\\Tygh\\Session', 'init'), &$_REQUEST),
        array('fn_init_ajax'),
        array('fn_init_company_id', &$_REQUEST),
        array('fn_check_cache', $_REQUEST),
        array('fn_init_settings'),
        array('fn_init_addons'),
        array('fn_get_route', &$_REQUEST),
        array('fn_simple_ultimate', &$_REQUEST)
    );

    if (!fn_allowed_for('ULTIMATE:FREE')) {
        fn_init_stack(array('fn_init_localization', &$_REQUEST));
    }

    fn_init_stack(array('fn_init_language', &$_REQUEST),
        array('fn_init_currency', &$_REQUEST),
        array('fn_init_company_data', $_REQUEST),
        array('fn_init_full_path', $_REQUEST),
        array('fn_init_layout', &$_REQUEST),
        array('fn_init_user'),
        array('fn_init_templater')
    );

    // Run INIT
    fn_init($_REQUEST);

    $stack = Registry::get('init_stack');

    // Cleanup stack
    Registry::set('init_stack', array());

    foreach ($stack as $function_data) {
        $function = array_shift($function_data);

        if (!is_callable($function)) {
            continue;
        }

        $result = call_user_func_array($function, $function_data);

        $status = !empty($result[0]) ? $result[0] : INIT_STATUS_OK;
        $url = !empty($result[1]) ? $result[1] : '';
        $message = !empty($result[2]) ? $result[2] : '';

        if ($status == INIT_STATUS_OK && !empty($url)) {
            $redirect_url = $url;

        } elseif ($status == INIT_STATUS_REDIRECT && !empty($url)) {
            $redirect_url = $url;
            break;

        } elseif ($status == INIT_STATUS_FAIL) {
            if (empty($message)) {
                $message = 'Initiation failed in <b>' . (is_array($function) ? implode('::', $function) : $function) . '</b> function';
            }
            die($message);
        }
    }

    /* if (!empty($redirect_url)) {
        if (!defined('CART_LANGUAGE')) {
            fn_init_language($request); // we need CART_LANGUAGE in fn_url function that called in fn_redirect
        }
        fn_redirect($redirect_url);
    } */

    $stack = Registry::get('init_stack');
    if (!empty($stack)) {
        // New init functions were added to stack. Execute them
        fn_init($request);
    }

    Debugger::init(true);
?>
