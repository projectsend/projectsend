<?php
/**
 * Build a simple HTML page with multiple providers.
 */
 ini_set('display_errors', 1);
 ini_set('display_startup_errors', 1);
 error_reporting(E_ALL);
include 'src/autoload.php';
include 'config.php';
require_once('../../sys.includes.php');
use Hybridauth\Hybridauth;

$hybridauth = new Hybridauth($config);
$adapters = $hybridauth->getConnectedAdapters();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Example 06</title>
</head>
<body>
<h1>Sign in</h1>

<ul>
    <?php foreach ($hybridauth->getProviders() as $name) : ?>
        <?php // if (!isset($adapters[$name])) : ?>
            <li>
                <a href="<?php print $config['callback'] . "?provider={$name}"; ?>">
                    Sign in with <strong><?php print $name; ?></strong>
                </a>
            </li>
        <?php // endif; ?>
    <?php endforeach; ?>
</ul>

<?php if ($adapters) : ?>
    <h1>You are logged in:</h1>
    <!-- <?php //print_r($adapters);  ?> -->
    <ul>
        <?php //foreach ($adapters as $name => $adapter) : ?>
            <!-- <li>
                <strong><?php //print $adapter->getUserProfile()->displayName; ?></strong> from -->
                <!-- <i><?php //print $name; ?></i> -->
                <!-- <span>(<a href="<?php //print $config['callback'] . "?logout={$name}"; ?>">Log Out</a>)</span> -->
            <!-- </li> -->
        <?php //endforeach; ?>
    </ul>
<?php endif; ?>
</body>
</html>
