<?php

/**
 * List of constants that should be read from the environnement settings. With Apache, these variable can be set using
 * the SetEnv directive. E.g.: SetEnv PASSWORD_PEPPER "my_pepper". Once they are registered in your project vhost, it
 * will be readable with the getenv PHP function. This make sure your private sensible information are not hardcoded
 * within the projet code base and thus publicly accessible. It also makes easier integration on various environnement
 * such as dev vs production.
 *
 * For environnement variables that are highly sensible such as the password pepper and encryption keys, they should be
 * random values of at least 32 characters unique for each project and cryptographically random. To generate a proper
 * randomized value, you should use a safe cryptographic random source. For more ideas of generation see
 * https://www.howtogeek.com/howto/30184/10-ways-to-generate-a-random-password-from-the-command-line/. Example:
 *
 * < /dev/urandom tr -dc _A-Z-a-z-0-9 | head -c32
 */

use Zephyrus\Application\Session;

define('DEFAULT_SESSION_SAVE_PATH', Session::DEFAULT_SAVE_PATH);

// Database username and password. If needed you could add an environnement variable for the database name and host if
// they are changing configurations depending on the environnement. Examples: the database name is not the same in prod
// and dev or the database is not on the localhost server in production environment, etc.
define('DB_USERNAME', getenv('DB_USERNAME'));
define('DB_PASSWORD', getenv('DB_PASSWORD'));

// Database or application level encryption should absolutely have its key in the server environnement.
define('ENCRYPTION_KEY', getenv('ENCRYPTION_KEY'));

// Automatically applies the pepper when using the Cryptography::hashPassword() and Cryptography::verifyHashedPassword()
// methods if this setting is configured in the config.ini file under the application section.
define('PASSWORD_PEPPER', getenv('PASSWORD_PEPPER'));
