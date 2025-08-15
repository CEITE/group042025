<?php
header('Content-Type: application/json');
echo json_encode([
  [
    "name"=>"Max","breed"=>"Aspin","age"=>"2 yrs",
    "illness"=>"Parvo","risk"=>"High","image"=>"https://placedog.net/200/200?id=1"
  ],
  [
    "name"=>"Luna","breed"=>"Pomeranian","age"=>"1 yr",
    "illness"=>"Worms","risk"=>"Medium","image"=>"https://placedog.net/200/200?id=2"
  ],
  [
    "name"=>"Buddy","breed"=>"Shih Tzu","age"=>"3 yrs",
    "illness"=>"None","risk"=>"Low","image"=>"https://placedog.net/200/200?id=3"
  ]
]);
