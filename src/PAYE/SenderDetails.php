<?php

namespace HMRC\PAYE;

class SenderDetails
{

    private $senderId  = '';
    private $password   = '';
    private $email      = '';

    public function __construct($senderId, $password, $email = null)
    {
        $this->senderId = $senderId;
        $this->password = $password;
        $this->email = $email;
    }

    public function getSenderId()
    {
        return $this->senderId;
    }

    public function setSenderId($value)
    {
        $this->senderId = $value;
    }

    public function getPassword()
    {
        return $this->password;
    }

    public function setPassword($value)
    {
        $this->password = $value;
    }

    public function getEmail()
    {
        return $this->email;
    }

    public function setEmail($value)
    {
        $this->email = $value;
    }
}
