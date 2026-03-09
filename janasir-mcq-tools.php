<?php
/**
 * Plugin Name: JanaSir MCQ Tools
 * Description: Interactive MCQ practice with cascading filters, CSV question banks, AJAX loading, and MathJax rendering.
 * Version: 1.0.0
 * Author: JanaSir
 * Text Domain: janasir-mcq-tools
 */

if (! defined('ABSPATH')) {
    exit;
}

define('JMT_PLUGIN_VERSION', '1.0.0');
define('JMT_PLUGIN_FILE', __FILE__);
define('JMT_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('JMT_PLUGIN_URL', plugin_dir_url(__FILE__));
define('JMT_UPLOAD_SUBDIR', 'janasir-mcq-tools');

require_once JMT_PLUGIN_PATH . 'includes/csv-loader.php';
require_once JMT_PLUGIN_PATH . 'includes/ajax-handler.php';

class JanaSir_MCQ_Tools {
    public function __construct() {
        add_action('init', [$this, 'register_shortcode']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_assets']);
        add_action('admin_menu', [$this, 'register_admin_menu']);
        add_action('admin_post_jmt_upload_csv', [$this, 'handle_csv_upload']);
        add_action('admin_post_jmt_delete_csv', [$this, 'handle_csv_delete']);
        add_action('admin_post_jmt_set_active_csv', [$this, 'handle_set_active_csv']);
    }

    public function register_shortcode() {
        add_shortcode('janasir_mcq_practice', [$this, 'render_practice_shortcode']);
    }

    public function enqueue_assets() {
        if (! is_singular()) {
            return;
        }

        global $post;
        if (! $post || ! has_shortcode((string) $post->post_content, 'janasir_mcq_practice')) {
            return;
        }

        wp_enqueue_style(
            'jmt-style',
            JMT_PLUGIN_URL . 'assets/css/style.css',
            [],
            JMT_PLUGIN_VERSION
        );

        wp_register_script(
            'jmt-mathjax',
            'https://cdn.jsdelivr.net/npm/mathjax@3/es5/tex-mml-chtml.js',
            [],
            null,
            true
        );

        wp_add_inline_script(
            'jmt-mathjax',
            'window.MathJax = { tex: { inlineMath: [["$", "$"], ["\\(", "\\)"]], displayMath: [["$$", "$$"], ["\\[", "\\]"]] } };',
            'before'
        );

        wp_enqueue_script('jmt-mathjax');

        wp_enqueue_script(
            'jmt-script',
            JMT_PLUGIN_URL . 'assets/js/script.js',
            ['jquery', 'jmt-mathjax'],
            JMT_PLUGIN_VERSION,
            true
        );

        wp_localize_script('jmt-script', 'JMT_CONFIG', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce'   => wp_create_nonce('jmt_nonce'),
            'messages' => [
                'loading' => __('Loading...', 'janasir-mcq-tools'),
                'noQuestions' => __('No questions found for selected criteria.', 'janasir-mcq-tools'),
            ],
        ]);
    }

    public function render_practice_shortcode() {
        ob_start();
        include JMT_PLUGIN_PATH . 'templates/practice-ui.php';
        return ob_get_clean();
    }

    public function register_admin_menu() {
        add_menu_page(
            __('JanaSir MCQ Tools', 'janasir-mcq-tools'),
            __('JanaSir MCQ Tools', 'janasir-mcq-tools'),
            'manage_options',
            'janasir-mcq-tools',
            [$this, 'render_admin_page'],
            'dashicons-welcome-learn-more',
            65
        );
    }

    public function render_admin_page() {
        if (! current_user_can('manage_options')) {
            wp_die(esc_html__('Unauthorized', 'janasir-mcq-tools'));
        }

        $files = jmt_get_csv_files();
        $active_file = get_option('jmt_active_csv_file', '');
        ?>
        <div class="wrap">
            <h1><?php echo esc_html__('JanaSir MCQ Tools - CSV Manager', 'janasir-mcq-tools'); ?></h1>

            <?php if (isset($_GET['jmt_status'])) : ?>
                <div class="notice notice-success is-dismissible"><p><?php echo esc_html(sanitize_text_field(wp_unslash($_GET['jmt_status']))); ?></p></div>
            <?php endif; ?>

            <h2><?php echo esc_html__('Upload CSV Question Bank', 'janasir-mcq-tools'); ?></h2>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" enctype="multipart/form-data">
                <?php wp_nonce_field('jmt_upload_csv', 'jmt_upload_nonce'); ?>
                <input type="hidden" name="action" value="jmt_upload_csv">
                <input type="file" name="jmt_csv_file" accept=".csv,text/csv" required>
                <?php submit_button(__('Upload CSV', 'janasir-mcq-tools')); ?>
            </form>

            <h2><?php echo esc_html__('Manage Question Files', 'janasir-mcq-tools'); ?></h2>
            <?php if (empty($files)) : ?>
                <p><?php echo esc_html__('No CSV files uploaded yet.', 'janasir-mcq-tools'); ?></p>
            <?php else : ?>
                <table class="widefat striped">
                    <thead>
                        <tr>
                            <th><?php echo esc_html__('File', 'janasir-mcq-tools'); ?></th>
                            <th><?php echo esc_html__('Rows (approx.)', 'janasir-mcq-tools'); ?></th>
                            <th><?php echo esc_html__('Status', 'janasir-mcq-tools'); ?></th>
                            <th><?php echo esc_html__('Actions', 'janasir-mcq-tools'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($files as $file) : ?>
                            <tr>
                                <td><?php echo esc_html($file['name']); ?></td>
                                <td><?php echo esc_html(number_format_i18n((int) $file['rows'])); ?></td>
                                <td><?php echo $file['path'] === $active_file ? '<strong>' . esc_html__('Active', 'janasir-mcq-tools') . '</strong>' : esc_html__('Inactive', 'janasir-mcq-tools'); ?></td>
                                <td>
                                    <form style="display:inline" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                                        <?php wp_nonce_field('jmt_set_active_csv', 'jmt_set_active_nonce'); ?>
                                        <input type="hidden" name="action" value="jmt_set_active_csv">
                                        <input type="hidden" name="file" value="<?php echo esc_attr($file['path']); ?>">
                                        <?php submit_button(__('Set Active', 'janasir-mcq-tools'), 'secondary', '', false); ?>
                                    </form>
                                    <form style="display:inline" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" onsubmit="return confirm('Are you sure?');">
                                        <?php wp_nonce_field('jmt_delete_csv', 'jmt_delete_nonce'); ?>
                                        <input type="hidden" name="action" value="jmt_delete_csv">
                                        <input type="hidden" name="file" value="<?php echo esc_attr($file['path']); ?>">
                                        <?php submit_button(__('Delete', 'janasir-mcq-tools'), 'delete', '', false); ?>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        <?php
    }

    public function handle_csv_upload() {
        if (! current_user_can('manage_options')) {
            wp_die(esc_html__('Unauthorized', 'janasir-mcq-tools'));
        }

        check_admin_referer('jmt_upload_csv', 'jmt_upload_nonce');

        if (empty($_FILES['jmt_csv_file']['name'])) {
            $this->redirect_with_status(__('No file selected.', 'janasir-mcq-tools'));
        }

        $upload = jmt_handle_csv_upload($_FILES['jmt_csv_file']);

        if (is_wp_error($upload)) {
            $this->redirect_with_status($upload->get_error_message());
        }

        if (! get_option('jmt_active_csv_file')) {
            update_option('jmt_active_csv_file', $upload['path']);
        }

        jmt_flush_cache();
        $this->redirect_with_status(__('CSV uploaded successfully.', 'janasir-mcq-tools'));
    }

    public function handle_csv_delete() {
        if (! current_user_can('manage_options')) {
            wp_die(esc_html__('Unauthorized', 'janasir-mcq-tools'));
        }

        check_admin_referer('jmt_delete_csv', 'jmt_delete_nonce');

        $file = isset($_POST['file']) ? sanitize_text_field(wp_unslash($_POST['file'])) : '';
        $deleted = jmt_delete_csv_file($file);

        if ($deleted) {
            $active = get_option('jmt_active_csv_file');
            if ($active === $file) {
                delete_option('jmt_active_csv_file');
            }
            jmt_flush_cache();
            $this->redirect_with_status(__('CSV deleted successfully.', 'janasir-mcq-tools'));
        }

        $this->redirect_with_status(__('Unable to delete CSV file.', 'janasir-mcq-tools'));
    }

    public function handle_set_active_csv() {
        if (! current_user_can('manage_options')) {
            wp_die(esc_html__('Unauthorized', 'janasir-mcq-tools'));
        }

        check_admin_referer('jmt_set_active_csv', 'jmt_set_active_nonce');

        $file = isset($_POST['file']) ? sanitize_text_field(wp_unslash($_POST['file'])) : '';

        if (! jmt_is_valid_csv_path($file)) {
            $this->redirect_with_status(__('Invalid CSV file.', 'janasir-mcq-tools'));
        }

        update_option('jmt_active_csv_file', $file);
        jmt_flush_cache();
        $this->redirect_with_status(__('Active CSV updated.', 'janasir-mcq-tools'));
    }

    private function redirect_with_status($message) {
        wp_safe_redirect(add_query_arg('jmt_status', rawurlencode($message), admin_url('admin.php?page=janasir-mcq-tools')));
        exit;
    }
}

new JanaSir_MCQ_Tools();
