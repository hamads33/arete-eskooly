<?php
declare(strict_types=1);
namespace App\Services;
interface SmsProviderInterface{public function send(string$recipient,string$message):string;}
