<?php
function houzez_child_display_property_price($output, $listing_id) {
    $sale_price = get_post_meta($listing_id, 'fave_property_price', true);
    $sale_price_max = get_post_meta($listing_id, 'fave_property_price_max', true);

    if (!empty($sale_price)) {
        if (is_singular('property')) {
            $output .= '<li class="item-price">';
            $output .= houzez_get_property_price($sale_price);
            $output .= '</li>';
        } else {
            $output .= '<li class="item-price">';
            $output .= houzez_get_property_price($sale_price);

            if (!empty($sale_price_max)) {
                $output .= "<br>" . houzez_get_property_price($sale_price_max);
            }

            $output .= '</li>';
        }
    }

    return $output;
}
add_filter('houzez_property_price_output', 'houzez_child_display_property_price', 10, 2);
