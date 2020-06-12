<?php

namespace WooMS;

// Exit if accessed directly
defined('ABSPATH') || exit;

/**
 * Abstract walker for commone methods
 */
abstract class AbstractWalker
{

    public static $state_transient_key;
    public static $walker_hook_name;

    /**
     * get state data
     */
    public static function get_state($key = '')
    {
        if (!$state = get_transient(self::$state_transient_key)) {
            $state = [];
            set_transient(self::$state_transient_key, $state);
        }

        if (empty($key)) {
            return $state;
        }

        if (empty($state[$key])) {
            return null;
        }

        return $state[$key];
    }

    /**
     * set state data
     */
    public static function set_state($key, $value)
    {

        if (!$state = get_transient(self::$state_transient_key)) {
            $state = [];
        }

        if (is_array($state)) {
            $state[$key] = $value;
        } else {
            $state = [];
            $state[$key] = $value;
        }

        set_transient(self::$state_transient_key, $state);
    }
}
