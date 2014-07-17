<?php
namespace Concrete\Core\Localization\Service;

use Request;
use Zend_Date;
use Zend_Locale;
use Cache;
use Localization;
use User;

class Date
{
    /**
	 * Gets the date time for the local time zone/area if user timezones are enabled, if not returns system datetime
	 * @param string $systemDateTime
	 * @param string $format
	 * @return string $datetime
	 */
    public function getLocalDateTime($systemDateTime = 'now', $mask = null)
    {
        if (!isset($mask) || !strlen($mask)) {
            $mask = 'Y-m-d H:i:s';
        }

        $req = Request::getInstance();
        if ($req->hasCustomRequestUser() && $req->getCustomRequestDateTime()) {
            return date($mask, strtotime($req->getCustomRequestDateTime()));
        }

        if (!isset($systemDateTime) || !strlen($systemDateTime)) {
            return NULL; // if passed a null value, pass it back
        } elseif (strlen($systemDateTime)) {
            $datetime = new \DateTime($systemDateTime);
        } else {
            $datetime = new \DateTime();
        }

        if (defined('ENABLE_USER_TIMEZONES') && ENABLE_USER_TIMEZONES) {
            $u = new User();
            if ($u && $u->isRegistered()) {
                $utz = $u->getUserTimezone();
                if ($utz) {
                    $tz = new \DateTimeZone($utz);
                    $datetime->setTimezone($tz);
                }
            }
        }
        if (Localization::activeLocale() != 'en_US') {
            return $this->dateTimeFormatLocal($datetime,$mask);
        } else {
            return $datetime->format($mask);
        }
    }

    /**
	 * Converts a user entered datetime to the system datetime
	 * @param string $userDateTime
	 * @param string $systemDateTime
	 * @return string $datetime
	 */
    public function getSystemDateTime($userDateTime = 'now', $mask = null)
    {
        if (!isset($mask) || !strlen($mask)) {
            $mask = 'Y-m-d H:i:s';
        }
        $req = Request::getInstance();
        if ($req->hasCustomRequestUser()) {
            return date($mask, strtotime($req->getCustomRequestDateTime()));
        }

        if (!isset($userDateTime) || !strlen($userDateTime)) {
            return NULL; // if passed a null value, pass it back
        }
        $datetime = new \DateTime($userDateTime);

        if (defined('APP_TIMEZONE')) {
            $tz = new \DateTimeZone(APP_TIMEZONE_SERVER);
            $datetime = new \DateTime($userDateTime,$tz); // create the in the user's timezone
            $stz = new \DateTimeZone(date_default_timezone_get()); // grab the default timezone
            $datetime->setTimeZone($stz); // convert the datetime object to the current timezone
        }

        if (defined('ENABLE_USER_TIMEZONES') && ENABLE_USER_TIMEZONES) {
            $u = new User();
            if ($u && $u->isRegistered()) {
                $utz = $u->getUserTimezone();
                if ($utz) {
                    $tz = new \DateTimeZone($utz);
                    $datetime = new \DateTime($userDateTime,$tz); // create the in the user's timezone

                    $stz = new \DateTimeZone(date_default_timezone_get()); // grab the default timezone
                    $datetime->setTimeZone($stz); // convert the datetime object to the current timezone
                }
            }
        }
        if (Localization::activeLocale() != 'en_US') {
            return $this->dateTimeFormatLocal($datetime,$mask);
        } else {
            return $datetime->format($mask);
        }
    }

    /**
	 * Gets the localized date according to a specific mask
	 * @param object $datetime A PHP DateTime Object
	 * @param string $mask
	 * @return string
	 */
    public function dateTimeFormatLocal(&$datetime,$mask)
    {
        $locale = new \Zend_Locale(Localization::activeLocale());

        $date = new \Zend_Date($datetime->format(DATE_ATOM),DATE_ATOM, $locale);
        $date->setTimeZone($datetime->format("e"));

        return $date->toString($mask);
    }

    /**
	 * Subsitute for the native date() function that adds localized date support
	 * This uses Zend's Date Object {@link http://framework.zend.com/manual/en/zend.date.constants.html#zend.date.constants.phpformats}
	 * @param string $mask
	 * @param int $timestamp
	 * @return string
	 */
    public function date($mask,$timestamp=false)
    {
        $loc = Localization::getInstance();
        if ($timestamp === false) {
            $timestamp = time();
        }

        if ($loc->getLocale() == 'en_US') {
            return date($mask, $timestamp);
        }

        $locale = new Zend_Locale(Localization::activeLocale());
        Zend_Date::setOptions(array('format_type' => 'php'));
        $cache = Cache::getLibrary();
        if (is_object($cache)) {
            Zend_Date::setOptions(array('cache'=>$cache));
        }
        $date = new Zend_Date($timestamp, false, $locale);

        return $date->toString($mask);
    }

    /**
	 * Returns a keyed array of timezone identifiers
	 * @return array
	 * @see http://www.php.net/datetimezone.listidentifiers.php
	 */
    public function getTimezones()
    {
        return array_combine(\DateTimeZone::listIdentifiers(),DateTimeZone::listIdentifiers());
    }

    /**
     * Describe the difference in time between now and a date/time in the past.
     * If the date/time is in the future or if it's more than one year old, you'll get the date representation of $posttime
     * @param  int    $posttime The timestamp to analyze
     * @param  bool   $precise  = false Set to true to a more verbose and precise result, false for a more rounded result
     * @return string
     */
    public function timeSince($posttime, $precise = false)
    {
        $diff = time() - $posttime;
        if (($diff < 0) || ($diff > 365 * 24 * 60 * 60)) {
            return $this->formatDate($posttime, false);
        } else {
            return $this->describeInterval($diff, $precise);
        }
    }

    /**
     * Returns the localized representation of a time interval specified as seconds.
     * @param  int    $diff    The time difference in seconds
     * @param  bool   $precise = false Set to true to a more verbose and precise result, false for a more rounded result
     * @return string
     */
    public function describeInterval($diff, $precise = false)
    {
        $secondsPerMinute = 60;
        $secondsPerHour = 60 * $secondsPerMinute;
        $secondsPerDay = 24 * $secondsPerHour;
        $days = floor($diff / $secondsPerDay);
        $diff = $diff - $days * $secondsPerDay;
        $hours = floor($diff / $secondsPerHour);
        $diff = $diff - $hours * $secondsPerHour;
        $minutes = floor($diff / $secondsPerMinute);
        $seconds = $diff - $minutes * $secondsPerMinute;
        if ($days > 0) {
            $description = t2('%d day', '%d days', $days, $days);
            if ($precise) {
                $description .= ', ' . t2('%d hour', '%d hours', $hours, $hours);
            }
        } elseif ($hours > 0) {
            $description = t2('%d hour', '%d hours', $hours, $hours);
            if ($precise) {
                $description .= ', ' . t2('%d minute', '%d minutes', $minutes, $minutes);
            }
        } elseif ($minutes > 0) {
            $description = t2('%d minute', '%d minutes', $minutes, $minutes);
            if ($precise) {
                $description .= ', '.t2('%d second', '%d seconds', $seconds, $seconds);
            }
        } else {
            $description = t2('%d second', '%d seconds', $seconds, $seconds);
        }

        return $description;
    }
}
