<?php
/*
Plugin Name: Custom Sites Post Type
Description: Test/Trial plugin to create a custom post type called "sites" and allow creation via WP REST API.
*/
function custom_sites_post_type() {
    $labels = array(
        'name'                  => _x( 'Sites', 'Post Type General Name', 'text_domain' ),
        'singular_name'         => _x( 'Site', 'Post Type Singular Name', 'text_domain' ),
        'menu_name'             => __( 'Sites', 'text_domain' ),
        'name_admin_bar'        => __( 'Site', 'text_domain' ),
        'all_items'             => __( 'All Sites', 'text_domain' ),
        'add_new_item'          => __( 'Add New Site', 'text_domain' ),
        'add_new'               => __( 'Add New', 'text_domain' ),
        'new_item'              => __( 'New Site', 'text_domain' ),
    );
    $args = array(
        'label'                 => __( 'Site', 'text_domain' ),
        'description'           => __( 'Sites Post Type', 'text_domain' ),
        'labels'                => $labels,
        'supports'              => array( 'title' ,'editor'),
        'hierarchical'          => false,
        'public'                => true,
        'show_ui'               => true,
        'show_in_menu'          => true,
        'menu_position'         => 5,
        'show_in_admin_bar'     => false,
        'show_in_nav_menus'     => false,
        'can_export'            => true,
        'has_archive'           => true,
        'exclude_from_search'   => false,
        'publicly_queryable'    => true,
        'capability_type'       => 'post',
    );
    register_post_type( 'sites', $args );
}
add_action( 'init', 'custom_sites_post_type', 0 );

// Custom REST API Endpoint to Create Sites
function create_site_via_rest_api( $request ) {
    $name = $request['name'];
    $website = $request['website'];

    $post_id = wp_insert_post( array(
        'post_title'   => $name,
        'post_content' => $website,
        'post_type'    => 'sites',
        'post_status'  => 'publish'
    ) );

    if ( is_wp_error( $post_id ) ) {
        wp_send_json_error('Form submission failed!');
    }else{
        wp_send_json_success('Submission successful!');
    }
}

add_action( 'rest_api_init', function () {
    register_rest_route( 'custom/v1', '/sites', array(
        'methods'  => 'POST',
        'callback' => 'create_site_via_rest_api',
    ) );
} );

// Prevent Editing and Deleting via Admin
function restrict_sites_post_type_editing( $caps, $cap, $user_id, $args ) {
    $current_user = wp_get_current_user();
    $user_roles = $current_user->roles;
    if ( in_array('editor', $user_roles) && ('edit_post' === $cap || 'delete_post' === $cap) ) {
        $post_id = $args[0];
        $post = get_post( $post_id );
        if ( 'sites' === $post->post_type ) {
            $caps[] = 'do_not_allow';
        }
    }

    if ( in_array('administrator', $user_roles) && 'delete_post' === $cap ) {
        $post_id = $args[0];
        $post = get_post( $post_id );
        if ( 'sites' === $post->post_type ) {
            $caps[] = 'do_not_allow';
        }
    }
    return $caps;
}
add_filter( 'map_meta_cap', 'restrict_sites_post_type_editing', 10, 4 );

add_action('admin_menu', 'remove_add_new_site_button');
function remove_add_new_site_button() {
    remove_submenu_page('edit.php?post_type=sites', 'post-new.php?post_type=sites');
    /* Only Admin and editors should see the sites post type in admin menu */
    $current_user = wp_get_current_user();
    $user_roles = $current_user->roles;

    if (in_array('administrator', $user_roles) || in_array('editor', $user_roles)) {
        return;
    }

    remove_menu_page('edit.php?post_type=sites');
}

function remove_default_meta_boxes() {
    remove_meta_box('submitdiv', 'sites', 'side');
}
add_action('admin_menu', 'remove_default_meta_boxes');

/* Adding form via Shortcode to any page */

function website_submission_form_shortcode() {
    ob_start();
    ?>
    <style>
        #website-submission-form {
            width:50%;
            margin:0 auto;
        }
        form#website-form input[type="text"]{
            width:100%;
            padding:5px;
            border-radius: 5px;
            font-size:17px;
        }
        form#website-form input[type="submit"]{
            padding:10px 20px;
            border-radius: 5px;
            font-size:14px;
            border:0;
            background-color:#006ce8;
            color:#FFF;
        }
    </style>
    <div id="website-submission-form">
        <div id="submission-message" class=""></div>
        <form id="website-form" method="post" action="/wp-json/custom/v1/sites">
            <p>
                <label for="name">Name:</label><br>
                <input type="text" id="name" name="name" required>
                <div id="name-error"></div>
            </p>
            <p>
                <label for="website">Website:</label><br>
                <input type="text" id="website" name="website" required>
                <div id="website-error"></div>
            </p>
            <p>
                <input type="submit" name="submit_website" value="Submit">
            </p>
        </form>
    </div>
    <script>
    jQuery(document).ready(function($) {
        $('#website-form').submit(function(event) {
            event.preventDefault();

            // Clear any previous error messages
            $('#submission-message').html('');

            // Get form data
            var formData = $(this).serialize();
            
            // Validate Name field
            var name = $('#name').val();
            if (!name.trim()) {
                $('#name-error').html('Name is required.');
                return; // Exit the function if name is empty
            } else {
                $('#name-error').html(''); // Clear error message if name is not empty
            }

            // Validate Website field
            var website = $('#website').val();
            if (!website.trim()) {
                $('#website-error').html('Website URL is required.');
                return; // Exit the function if website is empty
            } else {
                // Regular expression to check if the input is a valid URL
                var urlRegex = new RegExp('^(http:\/\/www\.|https:\/\/www\.|http:\/\/|https:\/\/)?[a-z0-9]+([\-\.]{1}[a-z0-9]+)*\.[a-z]{2,5}(:[0-9]{1,5})?(\/.*)?$', 'i');
                if (!urlRegex.test(website)) {
                    $('#website-error').html('Please enter a valid URL.');
                    return; // Exit the function if website is not a valid URL
                } else {
                    $('#website-error').html(''); // Clear error message if website is a valid URL
                }
            }

            // If all validations pass, submit the form
            $.ajax({
                type: 'POST',
                url: '<?php echo esc_url_raw(rest_url('custom/v1/sites')); ?>',
                data: formData,
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
                },
                success: function(response) {
                    if (response.success) {
                        $('#submission-message').html('<p>Submission successful!</p>');
                    } else {
                        $('#submission-message').html('<p>Error: ' + response.data.message + '</p>');
                    }
                },
                error: function(xhr, status, error) {
                    $('#submission-message').html('<p>Error: ' + xhr.responseText + '</p>');
                }
            });
        });
    });


    </script>
    <?php
    return ob_get_clean();
}
add_shortcode('website_submission_form', 'website_submission_form_shortcode');