<?php

namespace App\Models;

class SesEvent
{
    public $eventVersion;
    public $receipt;
    public $mail;
    public $eventSource;

    public function __construct($json)
    {
        $this->eventVersion = $json['eventVersion'];
        $this->receipt = $json['ses']['receipt'];
        $this->mail = $json['ses']['mail'];
        $this->eventSource = $json['eventSource'];
    }
}

