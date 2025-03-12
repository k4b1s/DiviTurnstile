<?php
/**
 * Admin Class for Divi Turnstile Integration
 */

// Si ce fichier est appelé directement, interrompre.
if (!defined('ABSPATH')) exit;

class Divi_Turnstile_Admin {

    /**
     * Constructeur
     */
    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        add_filter('plugin_action_links_' . DIVI_TURNSTILE_PLUGIN_BASENAME, array($this, 'add_settings_link'));
    }

    /**
     * Ajouter un menu au tableau de bord
     */
    public function add_admin_menu() {
        add_options_page(
            __('Divi Turnstile', 'divi-turnstile'),
            __('Divi Turnstile', 'divi-turnstile'),
            'manage_options',
            'divi-turnstile',
            array($this, 'display_admin_page')
        );
    }

    /**
     * Enregistrer les paramètres
     */
    public function register_settings() {
        register_setting(
            'divi_turnstile_options',
            'divi_turnstile_options',
            array($this, 'sanitize_options')
        );

        add_settings_section(
            'divi_turnstile_main',
            __('Configuration de Cloudflare Turnstile', 'divi-turnstile'),
            array($this, 'section_description'),
            'divi-turnstile'
        );

        add_settings_field(
            'site_key',
            __('Clé de site', 'divi-turnstile'),
            array($this, 'site_key_field'),
            'divi-turnstile',
            'divi_turnstile_main'
        );

        add_settings_field(
            'secret_key',
            __('Clé secrète', 'divi-turnstile'),
            array($this, 'secret_key_field'),
            'divi-turnstile',
            'divi_turnstile_main'
        );

        add_settings_field(
            'enable_pages',
            __('Pages supplémentaires', 'divi-turnstile'),
            array($this, 'enable_pages_field'),
            'divi-turnstile',
            'divi_turnstile_main'
        );
    }

    /**
     * Description de la section
     */
    public function section_description() {
        echo '<p>' . __('Configurez vos clés Cloudflare Turnstile pour le CAPTCHA.', 'divi-turnstile') . '</p>';
    }

    /**
     * Champ pour la clé de site
     */
    public function site_key_field() {
        $options = get_option('divi_turnstile_options');
        $site_key = isset($options['site_key']) ? $options['site_key'] : '';
        ?>
        <input type='text' name='divi_turnstile_options[site_key]' value='<?php echo esc_attr($site_key); ?>' class="regular-text">
        <p class="description"><?php _e('La clé de site publique fournie par Cloudflare Turnstile', 'divi-turnstile'); ?></p>
        <?php
    }

    /**
     * Champ pour la clé secrète
     */
    public function secret_key_field() {
        $options = get_option('divi_turnstile_options');
        $secret_key = isset($options['secret_key']) ? $options['secret_key'] : '';
        ?>
        <input type='text' name='divi_turnstile_options[secret_key]' value='<?php echo esc_attr($secret_key); ?>' class="regular-text">
        <p class="description"><?php _e('La clé secrète fournie par Cloudflare Turnstile', 'divi-turnstile'); ?></p>
        <?php
    }

    /**
     * Champ pour les pages supplémentaires
     */
    public function enable_pages_field() {
        $options = get_option('divi_turnstile_options');
        $enable_pages = isset($options['enable_pages']) ? $options['enable_pages'] : 'contact,nous-contacter';
        ?>
        <input type='text' name='divi_turnstile_options[enable_pages]' value='<?php echo esc_attr($enable_pages); ?>' class="regular-text">
        <p class="description"><?php _e('Liste des slugs de pages séparés par des virgules où activer Turnstile en plus des pages détectées automatiquement', 'divi-turnstile'); ?></p>
        <?php
    }

    /**
     * Sanitize des options
     */
    public function sanitize_options($input) {
        $sanitized_input = array();
        
        if (isset($input['site_key'])) {
            $sanitized_input['site_key'] = sanitize_text_field($input['site_key']);
        }
        
        if (isset($input['secret_key'])) {
            $sanitized_input['secret_key'] = sanitize_text_field($input['secret_key']);
        }
        
        if (isset($input['enable_pages'])) {
            $sanitized_input['enable_pages'] = sanitize_text_field($input['enable_pages']);
        }
        
        return $sanitized_input;
    }

    /**
     * Afficher la page d'administration
     */
    public function display_admin_page() {
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            
            <div class="card">
                <h2><?php _e('Guide d\'utilisation', 'divi-turnstile'); ?></h2>
                <p><?php _e('Ce plugin intègre automatiquement Cloudflare Turnstile dans vos formulaires de contact Divi pour lutter contre le spam.', 'divi-turnstile'); ?></p>
                <ol>
                    <li><?php _e('Créez un compte sur <a href="https://www.cloudflare.com/products/turnstile/" target="_blank">Cloudflare</a> si vous n\'en avez pas déjà un', 'divi-turnstile'); ?></li>
                    <li><?php _e('Créez un site Turnstile et récupérez vos clés', 'divi-turnstile'); ?></li>
                    <li><?php _e('Entrez vos clés ci-dessous et enregistrez', 'divi-turnstile'); ?></li>
                </ol>
            </div>

            <form method="post" action="options.php">
                <?php
                settings_fields('divi_turnstile_options');
                do_settings_sections('divi-turnstile');
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }

    /**
     * Ajouter un lien vers les paramètres dans la liste des plugins
     */
    public function add_settings_link($links) {
        $settings_link = '<a href="' . admin_url('options-general.php?page=divi-turnstile') . '">' . __('Paramètres', 'divi-turnstile') . '</a>';
        array_unshift($links, $settings_link);
        return $links;
    }
}