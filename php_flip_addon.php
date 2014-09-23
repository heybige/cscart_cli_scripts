<?php
	ini_set('memory_limit', '512M');

	require_once 'cli_lib.php';

	use Tygh\Addons\SchemesManager;
	use Tygh\Bootstrap;
	use Tygh\Registry;
	use Tygh\Debugger;

	if ( empty($argv[1]) ) {
		echo "php {$argv[0]} [addon]\n";
		exit;
	}

	// Check for multiple addons, or convert string to array
	if ( strpos($argv[1],":") !== false )
		$addons = explode(":",$argv[1]);
	else
		$addons = array($argv[1]);

	echo "php_flip_addon: " . serialize($addons) . "\n";

	$STACK = new SplStack();

	foreach($addons as $addon) {

		if ( !is_dir( Registry::get('config.dir.addons') . $addon) ) {
			echo Registry::get('config.dir.addons') . $addon . " does not exist - skipping..\n";
			continue;
		}

		uninstall($STACK,$addon);
	}

	foreach($STACK as $item) {
		$addon = $STACK->pop();
		fn_install_addon($addon,true,false);
		fn_update_addon_status($addon,'A',true,false);
		echo "INSTALL: {$addon}\n";
	}

	echo "DONE\n";
	exit;

	function uninstall(&$STACK,$addon) {

		$INSTALLED = array();

		$STACK->rewind();

		foreach($STACK as $item)
			$INSTALLED[] = $item;

		// Check dependencies for this $addon - recurse if necessary
        $dependencies = SchemesManager::getUninstallDependencies($addon);
        if (!empty($dependencies)) {
            foreach($dependencies as $shortcode => $name) {
				// print "$addon depends on $shortcode\n";
				if ( !in_array($shortcode,$INSTALLED) )
					uninstall($STACK,$shortcode);
				else
					echo "warning: $addon already uninstalled\n";
            }
        }

		if ( !IsSet($INSTALLED) || !in_array($addon,$INSTALLED) ) {

			$result = fn_uninstall_addon($addon,true);

			if ( empty($result) ) {
				echo "could not uninstall '$addon' - aborting...\n\n";
				echo "Re-installing previously uninstalled addons to restore system to good state\n";
				foreach($STACK as $item) {
					$addon = $STACK->pop();
					fn_install_addon($addon,true,false);
					fn_update_addon_status($addon,'A',true,false);
					print "INSTALL: {$addon}\n";
				}
				exit;
			}

			echo "UNINSTALL: $addon\n";
			$STACK->push($addon);
		}
	}
?>
