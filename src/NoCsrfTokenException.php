<?php
/**
 * Created by PhpStorm.
 * User: Fengyu CHEN
 * Date: 23/12/2017
 * Time: 23:37.
 */

namespace App;

class NoCsrfTokenException extends \Exception
{
    /**
     * NoCsrfTokenException constructor.
     */
    public function __construct()
    {
    }
}
