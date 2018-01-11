<?php

$app->post('/cats', \CatHandler::class . ':create');
$app->get('/cats', \CatHandler::class . ':index');
$app->get('/cats/{catId}', \CatHandler::class . ':get');
$app->put('/cats/{catId}', \CatHandler::class . ':update');
$app->delete('/cats/{catId}', \CatHandler::class . ':delete');
$app->post('/cats/{catId}/feed', \CatHandler::class . ':feed');
