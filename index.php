<?php

require 'vendor/autoload.php';
require 'src/crawler.php';

echo "<h1>A very simple crawler</h1>";

$crawler = new \Yab\Crawler\Crawler;

$dom = $crawler->crawl('http://yourwebsite.com', 10);

foreach ($dom->links() as $link) {
    if ($link['visited']) {
       echo '<a href="'.$link['url'].'">'.$link['url'].'</a><br>';
    }
}