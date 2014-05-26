<?php
    namespace RatingWidget\Api\Sdk\Exceptions;
    
    class OAuthException extends \RatingWidget\Api\Sdk\Exceptions\Exception
    {
        public function __construct($pResult)
        {
            parent::__construct($pResult);
        }
    }
?>
