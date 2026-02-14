<?php
/**
 * Plugin Name: QuickPin Admin
 * Description: Pin important pages to top of admin list with per-user support and AJAX toggle.
 * Version: 1.0.0
 * Author: Arthu
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class QP_Admin {

    public function __construct() {

        // Add star column
        add_filter( 'manage_pages_columns', [ $this, 'add_pin_column' ] );
        add_action( 'manage_pages_custom_column', [ $this, 'render_pin_column' ], 10, 2 );

        // AJAX toggle
        add_action( 'wp_ajax_qp_toggle_pin', [ $this, 'toggle_pin' ] );

        // Reorder pages
        add_action( 'pre_get_posts', [ $this, 'reorder_pages' ] );

        // Dashboard widget
        add_action( 'wp_dashboard_setup', [ $this, 'dashboard_widget' ] );
    }

    /* ---------------------------
       1️⃣ Add Pin Column
    --------------------------- */

    public function add_pin_column( $columns ) {
        $columns['quickpin'] = '⭐';
        return $columns;
    }

    public function render_pin_column( $column, $post_id ) {

        if ( $column !== 'quickpin' ) return;

        $user_id = get_current_user_id();
        $pinned  = get_user_meta( $user_id, '_qp_pinned_pages', true );
        $pinned  = is_array( $pinned ) ? $pinned : [];

        $is_pinned = in_array( $post_id, $pinned );

        echo '<span class="qp-star" 
                data-post="' . esc_attr( $post_id ) . '" 
                style="cursor:pointer;font-size:18px;">' .
                ( $is_pinned ? '⭐' : '☆' ) .
             '</span>';
    }

    /* ---------------------------
       2️⃣ AJAX Toggle
    --------------------------- */

    public function toggle_pin() {

        $post_id = intval( $_POST['post_id'] );
        $user_id = get_current_user_id();

        $pinned = get_user_meta( $user_id, '_qp_pinned_pages', true );
        $pinned = is_array( $pinned ) ? $pinned : [];

        if ( in_array( $post_id, $pinned ) ) {
            $pinned = array_diff( $pinned, [ $post_id ] );
            $status = 'removed';
        } else {
            $pinned[] = $post_id;
            $status = 'added';
        }

        update_user_meta( $user_id, '_qp_pinned_pages', $pinned );

        wp_send_json_success( $status );
    }

    /* ---------------------------
       3️⃣ Reorder Pages (Auto-Pin Top)
    --------------------------- */

    public function reorder_pages( $query ) {

        if ( ! is_admin() || ! $query->is_main_query() ) return;
        if ( $query->get( 'post_type' ) !== 'page' ) return;

        $user_id = get_current_user_id();
        $pinned  = get_user_meta( $user_id, '_qp_pinned_pages', true );

        if ( empty( $pinned ) ) return;

        add_filter( 'posts_orderby', function( $orderby ) use ( $pinned ) {
            global $wpdb;

            $ids = implode( ',', array_map( 'intval', $pinned ) );

            return "FIELD({$wpdb->posts}.ID, $ids) DESC, " . $orderby;
        });
    }

    /* ---------------------------
       4️⃣ Dashboard Widget
    --------------------------- */

    public function dashboard_widget() {
        wp_add_dashboard_widget(
            'quickpin_dashboard',
            '📌 Your Pinned Pages',
            [ $this, 'render_dashboard_widget' ]
        );
    }

    public function render_dashboard_widget() {

        $user_id = get_current_user_id();
        $pinned  = get_user_meta( $user_id, '_qp_pinned_pages', true );
        $pinned  = is_array( $pinned ) ? $pinned : [];

        if ( empty( $pinned ) ) {
            echo '<p>No pinned pages yet.</p>';
            return;
        }

        echo '<ul>';

        foreach ( $pinned as $post_id ) {
            echo '<li><a href="' . get_edit_post_link( $post_id ) . '">' .
                  esc_html( get_the_title( $post_id ) ) .
                 '</a></li>';
        }

        echo '</ul>';
    }
}

new QP_Admin();

/* ---------------------------
   5️⃣ Load AJAX Script
--------------------------- */

add_action( 'admin_footer-edit.php', function() {

    if ( get_current_screen()->post_type !== 'page' ) return;
    ?>

    <script>
    jQuery(document).ready(function($){

        $('.qp-star').on('click', function(){

            let star = $(this);
            let post_id = star.data('post');

            $.post(ajaxurl, {
                action: 'qp_toggle_pin',
                post_id: post_id
            }, function(response){

                if(response.success){
                    star.text(response.data === 'added' ? '⭐' : '☆');
                    location.reload(); // refresh to reorder
                }
            });

        });

    });
    </script>

    <?php
});
