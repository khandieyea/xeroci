<?php

  require __DIR__ . '/vendor/autoload.php';

  define('APPPATH','./');
  define('ENVIRONMENT','development');

  $client = new \XeroCi\clientPrivate([],['ratecontrol'=>true]);

  $response = $client->get('Organisation');

  echo ($response->getBody());
?>
