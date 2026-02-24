<?php
/**
 * Plugin Name: Interactive Post Search
 * Plugin URI: https://example.com
 * Description: Post search using WordPress Interactivity API
 * Version: 1.0.0
 * Requires at least: 6.5
 * Author: Your Name
 * Text Domain: interactive-post-search
 */

if (!defined('ABSPATH')) {
    exit;
}

class Interactive_Post_Search {
    
    public function __construct() {
        add_action('init', array($this, 'register_assets'));
        add_shortcode('interactive_search', array($this, 'render_search'));
        add_action('wp_ajax_ips_search', array($this, 'ajax_search'));
        add_action('wp_ajax_nopriv_ips_search', array($this, 'ajax_search'));
    }
    
    public function register_assets() {
        // Register Interactivity API script
        wp_register_script_module(
            'interactive-post-search-view',
            plugin_dir_url(__FILE__) . 'view.js',
            array('@wordpress/interactivity'),
            '1.0.0'
        );
        
        wp_register_style(
            'interactive-post-search',
            plugin_dir_url(__FILE__) . 'style.css',
            array(),
            '1.0.0'
        );
    }
    
    public function render_search($atts) {
        $atts = shortcode_atts(array(
            'posts_per_page' => 5,
            'post_type' => 'post'
        ), $atts);
        
        wp_enqueue_script_module('interactive-post-search-view');
        wp_enqueue_style('interactive-post-search');
        
        // Create unique ID for this instance
        $unique_id = 'ips-' . uniqid();
        
        // Initial state
        $state = array(
            'searchTerm' => '',
            'results' => array(),
            'isLoading' => false,
            'hasSearched' => false,
            'totalFound' => 0,
            'postsPerPage' => $atts['posts_per_page'],
            'postType' => $atts['post_type'],
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('ips_search_nonce')
        );
        
        ob_start();
        ?>
        <div 
            data-wp-interactive="interactive-post-search"
            data-wp-context='<?php echo wp_json_encode($state); ?>'
            class="ips-container"
        >
            <div class="ips-search-box">
                <input 
                    type="text"
                    class="ips-input"
                    placeholder="Search posts..."
                    data-wp-on--input="actions.updateSearchTerm"
                    data-wp-bind--value="context.searchTerm"
                />
                <button 
                    class="ips-button"
                    data-wp-on--click="actions.performSearch"
                    data-wp-bind--disabled="state.isButtonDisabled"
                >
                    Search
                </button>
            </div>
            
            <div 
                class="ips-loading"
                data-wp-class--ips-show="context.isLoading"
            >
                <span class="ips-spinner"></span>
                <span>Searching...</span>
            </div>
            
            <div 
                class="ips-message"
                data-wp-class--ips-show="state.showMinLengthMessage"
            >
                Please enter at least 3 characters to search.
            </div>
            
            <div 
                class="ips-results-header"
                data-wp-class--ips-show="state.showResultsHeader"
                data-wp-text="state.resultsHeaderText"
                data-wp-watch--1="callbacks.logCounter"
            ></div>
            
            <div class="ips-results-list">
                <template data-wp-each="context.results">
                    <div class="ips-result-item">
                        <h3 class="ips-result-title">
                            <a 
                                data-wp-bind--href="context.item.permalink"
                                data-wp-text="context.item.title"
                            ></a>
                        </h3>
                        <p 
                            class="ips-result-date"
                            data-wp-text="context.item.date"
                        ></p>
                        <p 
                            class="ips-result-excerpt"
                            data-wp-text="context.item.excerpt"
                        ></p>
                        <a 
                            class="ips-read-more"
                            data-wp-bind--href="context.item.permalink"
                        >
                            Read more →
                        </a>
                    </div>
                </template>
            </div>
            
            <div 
                class="ips-no-results"
                data-wp-class--ips-show="state.showNoResults"
            >
                No posts found matching your search.
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    public function ajax_search() {
        check_ajax_referer('ips_search_nonce', 'nonce');
        
        $search_term = sanitize_text_field($_POST['search_term']);
        $posts_per_page = intval($_POST['posts_per_page']);
        $post_type = sanitize_text_field($_POST['post_type']);
        
        if (strlen($search_term) < 3) {
            wp_send_json_success(array(
                'posts' => array(),
                'found' => 0
            ));
            return;
        }
        
        $args = array(
            's' => $search_term,
            'post_type' => $post_type,
            'posts_per_page' => $posts_per_page,
            'post_status' => 'publish'
        );
        
        $query = new WP_Query($args);
        
        if ($query->have_posts()) {
            $results = array();
            while ($query->have_posts()) {
                $query->the_post();
                $results[] = array(
                    'id' => get_the_ID(),
                    'title' => get_the_title(),
                    'excerpt' => wp_trim_words(get_the_excerpt(), 30),
                    'permalink' => get_permalink(),
                    'date' => get_the_date()
                );
            }
            wp_reset_postdata();
            
            wp_send_json_success(array(
                'posts' => $results,
                'found' => $query->found_posts
            ));
        } else {
            wp_send_json_success(array(
                'posts' => array(),
                'found' => 0
            ));
        }
    }
}

new Interactive_Post_Search();
