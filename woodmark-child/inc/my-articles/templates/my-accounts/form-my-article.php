<?php
// Load cities from JSON
$cities_path = get_stylesheet_directory() . '/inc/data/cities.json';
$cities_array = [];

if (file_exists($cities_path)) {
    $json = file_get_contents($cities_path);
    $decoded = json_decode($json, true);
    if (isset($decoded['cities']) && is_array($decoded['cities'])) {
        $cities_array = $decoded['cities'];
    }
}

// Hardcoded domains
$domains = [
    'Restaurant',
    'Hotel',
    'Agentie de turism',
    'Transport',
    'Livrarea alimentelor',
    'Asistență medicală',
    'Casa si gradina',
    'Coaching',
    'Activitati copii',
    'Psiholog',
    'Altele'
];
?>

<h3>Trimite și un articol nou</h3>
<form method="post" enctype="multipart/form-data">
    <?php wp_nonce_field('wrac_submit_article', 'wrac_article_nonce'); ?>

    <p>
        <label for="article_title">Titlul:</label><br>
        <input type="text" name="article_title" id="article_title" required>
    </p>

    <p>
    <label for="article_content">Conținutul:</label><br>
    <?php
    wp_editor(
        '', // Initial content (blank for new submission)
        'article_content', // HTML `id` and `name` for the textarea
        [
            'textarea_name' => 'article_content',
            'textarea_rows' => 10,
            'media_buttons' => false, // set to true if you want media upload
            'teeny'         => true,  // simple version of the editor
        ]
    );
    ?>
</p>

    <p>
        <label for="article_city">Oraș:</label><br>
        <select name="article_city" id="article_city" required>
            <option value="">-- Selectează oraș --</option>
            <?php foreach ($cities_array as $city): ?>
                <option value="<?php echo esc_attr($city); ?>" class="notranslate"><?php echo esc_html($city); ?></option>
            <?php endforeach; ?>
        </select>
    </p>

    <p>
        <label for="article_domain">Domeniu:</label><br>
        <select name="article_domain" id="article_domain" required>
            <option value="">-- Selectează domeniul --</option>
            <?php foreach ($domains as $domain): ?>
                <option value="<?php echo esc_attr($domain); ?>"><?php echo esc_html($domain); ?></option>
            <?php endforeach; ?>
        </select>
    </p>

    <p>
        <label for="featured_image">Imagine prezentată:</label><br>
        <input type="file" name="featured_image" id="featured_image" accept="image/*">
    </p>

    <p>
        <button type="submit">Trimite Articol</button>
    </p>
</form>