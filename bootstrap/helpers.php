<?php

if (!function_exists('team')) {
    function team()
    {
        static $team;

        if (!$team) {
            $team = auth()->user()->team;
        }

        return $team;
    }
}
