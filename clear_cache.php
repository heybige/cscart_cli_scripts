<?php
	require_once 'cli_lib.php';
	
    use Tygh\Bootstrap;
    use Tygh\Registry;
    use Tygh\Debugger;

	fn_clear_cache();
	fn_rm(Registry::get('config.dir.var') . 'cache');
?>
