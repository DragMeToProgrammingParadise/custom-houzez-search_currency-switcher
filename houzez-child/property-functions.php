<?php
function houzez_child_save_property_price_max($post_id) {
    if (isset($_POST['prop_price_max'])) {
        update_post_meta($post_id, 'fave_property_price_max', sanitize_text_field($_POST['prop_price_max']));
    }
}
add_action('save_post_property', 'houzez_child_save_property_price_max');
