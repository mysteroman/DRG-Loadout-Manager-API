<?php

use Zephyrus\Utilities\Formatter;

/**
 * Defines a list of custom formats used for the project. Once a format is registered in the Formatter instance, it can
 * be used within Pug files or PHP files using the "format()" function using the custom name as the first argument. The
 * example below defines a new format called "date_full" which can now be called using : format("date_full", var). Very
 * useful for defining the formats applying to your project.
 */
Formatter::register('date_full', function ($dateTime) {
    if (!$dateTime instanceof \DateTime) {
        $dateTime = new DateTime($dateTime);
    }
    return strftime("%A %e %B", $dateTime->getTimestamp());
});
