<?php

// region [Composer autoloading and environnement]
// This part is essential for the correct inclusion of the Framework on part with Composer dependency manager. Do not
// modify. Also make sure to properly include the environnement variables.
define('ROOT_DIR', realpath(__DIR__ . '/..'));
require ROOT_DIR . '/vendor/autoload.php';
require "env.php";
// endregion

// region [Zephyrus instance and routing]
// Prepares the mandatory router instance for the execution of the Controller class route methods and launch the
// Zephyrus bootstrap based on the configurations.
use Zephyrus\Application\ErrorHandler;
use Zephyrus\Exceptions\DatabaseException;
use Zephyrus\Exceptions\RouteNotFoundException;
use Zephyrus\Exceptions\SessionException;
use Zephyrus\Network\Router;
use Zephyrus\Application\Bootstrap;
use Zephyrus\Application\Session;
use Zephyrus\Application\Localization;
use Zephyrus\Exceptions\LocalizationException;
$router = new Router();
include(Bootstrap::getHelperFunctionsPath());
Bootstrap::start();
// endregion

// region [Session startup]
// Optional if your project does not require a session. E.g. an API. Will launch the session with the configured
// settings in the config.ini file (e.g. encryption, fingerprints, etc.)
try {
    Session::getInstance()->start();
} catch (SessionException $e) {
    // Define what to do in case the session has a problem (e.g. fingerprint invalid)
    die($e->getMessage());
}
// endregion

// region [Localisation engine]
// Optional if you don't want to use the /locale feature. This features enables the use of json files to properly
// organize project messages whether you have multiple languages or not. It is thus highly recommended for a more clean
// and maintainable codebase.
try {

    // The <locale> argument is optional, if none is given the configured locale in config.ini will be used.
    Localization::getInstance()->start('fr_CA');
} catch (LocalizationException $e) {

    // If engine cannot properly start an exception will be thrown and must be corrected to use this feature. Common
    // errors are syntax error in json files. The exception message should be explicit enough.
    die($e->getMessage());
}
// endregion

// region [Custom inclusions]
// Adds a list of required files for this project. Default should have at least one file dedicated for added custom
// global functions and one file for added custom formats.
require "functions.php";
require "formats.php";
// endregion

// region [Custom error handling]
// Uncomment the setupErrorHandling() line to enabled a custom management of error. You will then need to define how
// certain types of errors should be handled.
//setupErrorHandling();

/**
 * Defines how to handle errors and exceptions which reached the main thread (that nobody trapped). These are usage
 * examples and should be altered to reflect serious application usage. The ErrorHandler class allows to handle any
 * specific exception as you see fit. This section could either be moved in a separate file or wrapped in a elegant
 * class depending on the complexity.
 *
 * Note that using the ErrorHandler changes the way PHP will handle errors at its core if you use notice(), warning()
 * or error().
 */
function setupErrorHandling()
{
    $errorHandler = ErrorHandler::getInstance();

    /**
     * Handles basically every exception that were not caught.
     */
    $errorHandler->exception(function (Exception $e) {
    });

    /**
     * Handles specific case when a database exception occurred. Depends on
     * the need of each application. Some may want to specifically handle
     * this case or catch them in the global Exception. In fact, it is
     * possible to handle every exception specifically if needed.
     */
    $errorHandler->exception(function (DatabaseException $e) {
    });

    /**
     * Handles when a user tries to access a route that doesn't exists. In
     * this example, it simply returns a 404 header. You could implement a
     * custom page to display a significant error, do a flash message and
     * redirect, you could also log the attempt, etc. The exception
     * contains the requested URL and http method.
     */
    $errorHandler->exception(function (RouteNotFoundException $e) {
    });
}

// endregion
