<?php
namespace App\Core;

class ValidationException extends \RuntimeException
{
    public function __construct(public array $errors)
    {
        parent::__construct('The form has errors.');
    }
}
