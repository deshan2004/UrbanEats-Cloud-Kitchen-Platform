<?php
declare(strict_types=1);

$dishes = [
    'chicken_kottu.jpg' => ['title' => 'Chicken Kottu', 'cat' => 'Kottu Specialties', 'color' => [235, 94, 40], 'icon' => '🍲'],
    'seafood_cheese_kottu.jpg' => ['title' => 'Seafood Cheese Kottu', 'cat' => 'Kottu Specialties', 'color' => [242, 142, 43], 'icon' => '🧀'],
    'beef_kottu.jpg' => ['title' => 'Roast Beef Kottu', 'cat' => 'Kottu Specialties', 'color' => [180, 50, 30], 'icon' => '🥩'],
    'veg_kottu.jpg' => ['title' => 'Veg Palandi Kottu', 'cat' => 'Kottu Specialties', 'color' => [46, 139, 87], 'icon' => '🥗'],
    'lamprais.jpg' => ['title' => 'Lankan Lamprais', 'cat' => 'Rice & Biryani', 'color' => [100, 140, 60], 'icon' => '🍃'],
    'chicken_biryani.jpg' => ['title' => 'Chicken Dum Biryani', 'cat' => 'Rice & Biryani', 'color' => [220, 120, 30], 'icon' => '🍚'],
    'mutton_biryani.jpg' => ['title' => 'Royal Mutton Biryani', 'cat' => 'Rice & Biryani', 'color' => [190, 80, 35], 'icon' => '🍖'],
    'seafood_rice.jpg' => ['title' => 'Mixed Seafood Rice', 'cat' => 'Rice & Biryani', 'color' => [210, 105, 30], 'icon' => '🦐'],
    'crab_curry.jpg' => ['title' => 'Jaffna Fiery Crab Curry', 'cat' => 'Traditional Curries', 'color' => [200, 40, 20], 'icon' => '🦀'],
    'ambul_thiyal.jpg' => ['title' => 'Fish Ambul Thiyal', 'cat' => 'Traditional Curries', 'color' => [80, 60, 50], 'icon' => '🐟'],
    'cashew_curry.jpg' => ['title' => 'Creamy Cashew Curry', 'cat' => 'Traditional Curries', 'color' => [230, 180, 80], 'icon' => '🥜'],
    'black_pork.jpg' => ['title' => 'Claypot Black Pork', 'cat' => 'Traditional Curries', 'color' => [70, 40, 30], 'icon' => '🥘'],
    'hbc.jpg' => ['title' => 'Hot Butter Cuttlefish', 'cat' => 'Street Bites & Roti', 'color' => [240, 100, 20], 'icon' => '🦑'],
    'pol_roti.jpg' => ['title' => 'Village Pol Roti', 'cat' => 'Street Bites & Roti', 'color' => [210, 160, 90], 'icon' => '🫓'],
    'mutton_rolls.jpg' => ['title' => 'Crispy Mutton Rolls', 'cat' => 'Street Bites & Roti', 'color' => [200, 110, 40], 'icon' => '🥟'],
    'hoppers.jpg' => ['title' => 'Egg Hoppers Platter', 'cat' => 'Street Bites & Roti', 'color' => [240, 200, 120], 'icon' => '🍳'],
    'watalappan.jpg' => ['title' => 'Kithul Watalappan', 'cat' => 'Artisan Desserts', 'color' => [120, 60, 30], 'icon' => '🍮'],
    'biscuit_pudding.jpg' => ['title' => 'Caramel Biscuit Pudding', 'cat' => 'Artisan Desserts', 'color' => [140, 80, 45], 'icon' => '🍫'],
    'curd_treacle.jpg' => ['title' => 'Buffalo Curd & Treacle', 'cat' => 'Artisan Desserts', 'color' => [220, 160, 70], 'icon' => '🍯'],
    'mango_tart.jpg' => ['title' => 'Mango Passion Tart', 'cat' => 'Artisan Desserts', 'color' => [245, 160, 30], 'icon' => '🥭'],
    'faluda.jpg' => ['title' => 'Royal Faluda', 'cat' => 'Beverages', 'color' => [225, 70, 120], 'icon' => '🍧'],
    'milk_tea.jpg' => ['title' => 'Ceylon Cardamom Tea', 'cat' => 'Beverages', 'color' => [180, 110, 60], 'icon' => '☕'],
    'thambili.jpg' => ['title' => 'Fresh King Coconut', 'cat' => 'Beverages', 'color' => [230, 140, 40], 'icon' => '🥥'],
    'lime_soda.jpg' => ['title' => 'Lime & Mint Soda', 'cat' => 'Beverages', 'color' => [60, 160, 100], 'icon' => '🍋'],
];

$dir = dirname(__DIR__) . '/public/uploads';
if (!is_dir($dir)) {
    mkdir($dir, 0755, true);
}

foreach ($dishes as $file => $info) {
    $path = $dir . '/' . $file;
    if (file_exists($path)) {
        continue;
    }

    $w = 600;
    $h = 400;
    $img = imagecreatetruecolor($w, $h);

    // Warm radial/gradient effect
    $r = $info['color'][0];
    $g = $info['color'][1];
    $b = $info['color'][2];

    for ($y = 0; $y < $h; $y++) {
        $factor = 0.75 + (0.25 * ($y / $h));
        $color = imagecolorallocate($img, (int)($r * $factor), (int)($g * $factor), (int)($b * $factor));
        imageline($img, 0, $y, $w, $y, $color);
    }

    // Overlay card in center
    $cardBg = imagecolorallocatealpha($img, 255, 255, 255, 25);
    imagefilledrectangle($img, 40, 40, $w - 40, $h - 40, $cardBg);

    // Border
    $borderColor = imagecolorallocatealpha($img, 255, 255, 255, 60);
    imagerectangle($img, 40, 40, $w - 40, $h - 40, $borderColor);

    // Text labels
    $textColor = imagecolorallocate($img, 255, 255, 255);
    $subColor = imagecolorallocate($img, 255, 240, 220);

    // Draw string using built-in font
    imagestring($img, 5, 60, 70, "UrbanEats Cloud Kitchen", $subColor);
    imagestring($img, 5, 60, 100, "[" . strtoupper($info['cat']) . "]", $subColor);
    imagestring($img, 5, 60, 150, $info['title'], $textColor);
    imagestring($img, 4, 60, 190, "Authentic Prepared Fresh Daily", $subColor);
    imagestring($img, 3, 60, $h - 80, "LKR • Fast Delivery & Pickup", $textColor);

    imagejpeg($img, $path, 90);
    imagedestroy($img);
}

echo "Created seed dish images successfully.\n";
