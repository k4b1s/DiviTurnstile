<?php
/**
 * Plugin Name: Divi Turnstile Integration
 * Plugin URI: https://example.com/divi-turnstile
 * Description: Intègre Cloudflare Turnstile dans les formulaires de contact Divi
 * Version: 1.0.0
 * Author: K4b1s
 * Author URI: https://example.com
 * Text Domain: divi-turnstile
 * Domain Path: /languages
 * Requires at least: 5.0
 * Requires PHP: 7.2
 */

// Si ce fichier est appelé directement, interrompre.
if (!defined('ABSPATH')) exit;

// Définir les constantes du plugin
define('DIVI_TURNSTILE_VERSION', '1.0.0');
define('DIVI_TURNSTILE_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('DIVI_TURNSTILE_PLUGIN_URL', plugin_dir_url(__FILE__));
define('DIVI_TURNSTILE_PLUGIN_BASENAME', plugin_basename(__FILE__));

/**
 * Classe principale du plugin
 */
class Divi_Turnstile_Integration {

    /**
     * Instance unique
     */
    private static $instance = null;

    /**
     * Créer une instance unique
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructeur
     */
    private function __construct() {
        // Activer l'internationalisation
        add_action('plugins_loaded', array($this, 'load_plugin_textdomain'));

        // Charger l'administration
        if (is_admin()) {
            require_once DIVI_TURNSTILE_PLUGIN_DIR . 'includes/class-divi-turnstile-admin.php';
            $admin = new Divi_Turnstile_Admin();
        }

        // Hooks pour l'intégration du formulaire
        add_action('wp_footer', array($this, 'add_turnstile_to_divi'));
        add_action('wp_head', array($this, 'add_turnstile_styles'));
        add_filter('et_contact_form_is_spam', array($this, 'verify_divi_turnstile'), 10, 3);
        
        // Hooks pour interception AJAX
        add_action('init', array($this, 'check_turnstile_before_submit'), 1);
        add_action('wp_ajax_et_pb_submit_subscribe_form', array($this, 'check_turnstile_before_submit'), 1);
        add_action('wp_ajax_nopriv_et_pb_submit_subscribe_form', array($this, 'check_turnstile_before_submit'), 1);
        add_action('wp_ajax_et_pb_contact_form_submit', array($this, 'check_turnstile_before_submit'), 1);
        add_action('wp_ajax_nopriv_et_pb_contact_form_submit', array($this, 'check_turnstile_before_submit'), 1);
    }

    /**
     * Chargement des traductions
     */
    public function load_plugin_textdomain() {
        load_plugin_textdomain('divi-turnstile', false, dirname(DIVI_TURNSTILE_PLUGIN_BASENAME) . '/languages');
    }

    /**
     * Récupérer les clés Turnstile
     */
    public function get_turnstile_keys() {
        $options = get_option('divi_turnstile_options', array());
        return [
            'site_key'   => !empty($options['site_key']) ? $options['site_key'] : '0x4AAAAAABASNytVIyRKRmt5',
            'secret_key' => !empty($options['secret_key']) ? $options['secret_key'] : '0x4AAAAAABASN31xWED_HJuuFX3jtJF0MZ0'
        ];
    }

    /**
     * Détecter si la page contient un formulaire Divi
     */
    public function page_has_divi_form() {
        global $post;
        
        // Toujours activer sur la page d'accueil
        if (is_front_page()) return true;
        
        // Activer sur les pages WooCommerce
        if (function_exists('is_shop') && is_shop()) return true;
        if (function_exists('is_product') && is_product()) return true;
        if (function_exists('is_woocommerce') && is_woocommerce()) return true;
        
        // Vérification standard pour les autres pages
        if (is_singular() && is_a($post, 'WP_Post')) {
            return has_shortcode($post->post_content, 'et_pb_contact_form') || 
                  has_shortcode($post->post_content, 'et_pb_contact_form_container') ||
                  get_post_meta($post->ID, '_et_pb_use_builder', true) === 'on';
        }
        
        // Pages spécifiques par slug
        $options = get_option('divi_turnstile_options', array());
        $always_enable_on_pages = !empty($options['enable_pages']) ? 
                                 explode(',', sanitize_text_field($options['enable_pages'])) : 
                                 array('contact', 'nous-contacter');
        
        if (is_singular('page') && isset($post->post_name) && in_array($post->post_name, $always_enable_on_pages)) {
            return true;
        }
        
        return false;
    }

    /**
     * Ajouter Cloudflare Turnstile aux formulaires de contact Divi
     */
    public function add_turnstile_to_divi() {
        if (!$this->page_has_divi_form()) return;
        
        $keys = $this->get_turnstile_keys();
        wp_enqueue_script('cloudflare-turnstile', 'https://challenges.cloudflare.com/turnstile/v0/api.js', array(), null, true);
        
        ?>
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            // Variables globales pour stocker les widgets et les jetons
            window.diviTurnstileWidgets = {};
            window.diviTurnstileTokens = {};
            window.originalDiviSubmit = null;
            
            function initTurnstile() {
                if ($('.et_pb_contact_form').length === 0) return;
                
                $('.et_pb_contact_form').each(function(index) {
                    var formId = 'divi-form-' + index;
                    var $form = $(this);
                    
                    // Attribuer un ID unique au formulaire si nécessaire
                    if (!$form.attr('id')) {
                        $form.attr('id', formId);
                    } else {
                        formId = $form.attr('id');
                    }
                    
                    // Éviter les doublons
                    if ($form.find('.cf-turnstile').length === 0) {
                        // Ajouter le conteneur pour le widget
                        $form.find('.et_contact_bottom_container').before('<div class="cf-turnstile" id="cf-wrapper-' + formId + '"></div>');
                        
                        // Créer l'input caché avec un ID unique
                        var hiddenInputId = 'cf-turnstile-response-' + formId;
                        $form.append('<input type="hidden" name="cf-turnstile-response" id="' + hiddenInputId + '" value="">');
                        
                        // Attendre que Turnstile soit chargé
                        if (typeof window.turnstile !== 'undefined') {
                            try {
                                // Rendre le widget Turnstile et stocker l'ID du widget
                                var widgetId = window.turnstile.render('#cf-wrapper-' + formId, {
                                    sitekey: '<?php echo esc_attr($keys['site_key']); ?>',
                                    callback: function(token) {
                                        console.log("Turnstile validé pour le formulaire " + formId + ", token: " + token.substring(0, 10) + "...");
                                        window.diviTurnstileTokens[formId] = token;
                                        $('#' + hiddenInputId).val(token);
                                    }
                                });
                                window.diviTurnstileWidgets[formId] = widgetId;
                            } catch(e) {
                                console.error('Erreur lors du rendu de Turnstile:', e);
                            }
                        }
                    }
                });
            }
            
            // Remplacer la fonction de soumission de Divi
            function overrideDiviSubmit() {
                if (window.originalDiviSubmit) return; // Déjà remplacé
                
                if (typeof window.et_pb_submit_form === 'function') {
                    // Sauvegarder la fonction originale
                    window.originalDiviSubmit = window.et_pb_submit_form;
                    
                    // Remplacer par notre fonction sécurisée
                    window.et_pb_submit_form = function($form) {
                        var formId = $form.attr('id');
                        var token = window.diviTurnstileTokens[formId] || $form.find('input[name="cf-turnstile-response"]').val();
                        
                        if (!token) {
                            alert("<?php echo esc_js(__('Veuillez valider le CAPTCHA avant d\'envoyer le formulaire.', 'divi-turnstile')); ?>");
                            
                            // Réactiver le bouton si désactivé
                            $form.find('.et_pb_contact_submit').prop('disabled', false).removeClass('et_contact_processing');
                            
                            // Ne pas soumettre le formulaire
                            return false;
                        }
                        
                        // Si le token existe, appeler la fonction d'origine
                        return window.originalDiviSubmit.apply(this, arguments);
                    };
                    console.log("Divi submit function overridden successfully");
                }
            }
            
            // Attendre que Divi charge ses fonctions
            function checkDiviLoaded() {
                if (typeof window.et_pb_submit_form === 'function') {
                    overrideDiviSubmit();
                } else {
                    setTimeout(checkDiviLoaded, 500);
                }
            }
            
            // Démarrer les vérifications
            setTimeout(checkDiviLoaded, 500);
            
            // Essayer d'initialiser à plusieurs moments clés
            $(window).on('et_builder_api_ready', function() {
                setTimeout(function() {
                    initTurnstile();
                    overrideDiviSubmit();
                }, 100);
            });
            
            // Initialisation au chargement de la page
            setTimeout(function() {
                initTurnstile();
                overrideDiviSubmit();
            }, 1000);
            
            // Surveiller les chargements Ajax
            $(document).ajaxComplete(function() {
                setTimeout(initTurnstile, 300);
            });
            
            // Intercepter aussi les clicks sur le bouton de soumission
            $(document).on('click', '.et_pb_contact_submit', function(e) {
                var $form = $(this).closest('.et_pb_contact_form');
                var formId = $form.attr('id');
                var token = window.diviTurnstileTokens[formId] || $form.find('input[name="cf-turnstile-response"]').val();
                
                if (!token) {
                    e.preventDefault();
                    e.stopImmediatePropagation();
                    alert("<?php echo esc_js(__('Veuillez valider le CAPTCHA avant d\'envoyer le formulaire.', 'divi-turnstile')); ?>");
                    return false;
                }
            });
        });
        </script>
        <?php
    }

    /**
     * Ajouter les styles CSS pour le widget Turnstile
     */
    public function add_turnstile_styles() {
        if (!$this->page_has_divi_form()) return;
        ?>
        <style>
        .cf-turnstile {
            margin: 15px 0;
            display: flex;
            justify-content: flex-start;
        }
        @media (max-width: 767px) {
            .cf-turnstile {
                justify-content: center;
            }
        }
        </style>
        <?php
    }

    /**
     * Vérifier la réponse Turnstile (filtre Divi)
     */
    public function verify_divi_turnstile($is_spam, $message, $contact_form_data) {
        // Si le formulaire est déjà marqué comme spam, ne pas continuer
        if ($is_spam) return true;
        
        // Récupérer la réponse Turnstile
        $turnstile_response = isset($_POST['cf-turnstile-response']) ? sanitize_text_field($_POST['cf-turnstile-response']) : '';
        
        // Si pas de réponse, considérer comme spam
        if (empty($turnstile_response)) {
            error_log('Divi form spam: No Turnstile response provided');
            return true; 
        }
        
        // Vérifier la réponse avec l'API Cloudflare
        $keys = $this->get_turnstile_keys();
        $verify = wp_remote_post('https://challenges.cloudflare.com/turnstile/v0/siteverify', [
            'body' => [
                'secret' => $keys['secret_key'],
                'response' => $turnstile_response,
                'remoteip' => $_SERVER['REMOTE_ADDR']
            ]
        ]);
        
        if (is_wp_error($verify)) {
            error_log('Divi form spam: Error verifying Turnstile: ' . $verify->get_error_message());
            return true;
        }
        
        $response_code = wp_remote_retrieve_response_code($verify);
        if ($response_code !== 200) {
            error_log('Divi form spam: Turnstile verification failed with status ' . $response_code);
            return true;
        }
        
        $verify_result = json_decode(wp_remote_retrieve_body($verify), true);
        if (!isset($verify_result['success']) || $verify_result['success'] !== true) {
            error_log('Divi form spam: Turnstile validation failed: ' . json_encode($verify_result));
            return true;
        }
        
        // Vérification réussie
        return false;
    }

    /**
     * Arrêter complètement le traitement Ajax si pas de CAPTCHA valide
     */
    public function check_turnstile_before_submit() {
        // Vérifier si c'est une action de formulaire Divi
        if (
            (isset($_POST['action']) && ($_POST['action'] === 'et_pb_contact_form_submit' || $_POST['action'] === 'et_pb_submit_subscribe_form')) ||
            (isset($_POST['et_pb_contact_email']))
        ) {
            $turnstile_response = isset($_POST['cf-turnstile-response']) ? sanitize_text_field($_POST['cf-turnstile-response']) : '';
            
            if (empty($turnstile_response)) {
                status_header(403);
                wp_send_json_error([
                    'message' => __('CAPTCHA non validé', 'divi-turnstile')
                ]);
                exit;
            }
            
            $keys = $this->get_turnstile_keys();
            $verify = wp_remote_post('https://challenges.cloudflare.com/turnstile/v0/siteverify', [
                'body' => [
                    'secret' => $keys['secret_key'],
                    'response' => $turnstile_response,
                    'remoteip' => $_SERVER['REMOTE_ADDR']
                ]
            ]);
            
            $verification_failed = is_wp_error($verify) || 
                                  wp_remote_retrieve_response_code($verify) !== 200;
                                  
            if (!$verification_failed) {
                $verify_result = json_decode(wp_remote_retrieve_body($verify), true);
                $verification_failed = !isset($verify_result['success']) || $verify_result['success'] !== true;
            }
            
            if ($verification_failed) {
                status_header(403);
                wp_send_json_error([
                    'message' => __('Échec de validation CAPTCHA', 'divi-turnstile')
                ]);
                exit;
            }
        }
    }
}

/**
 * Fonction d'activation du plugin
 */
function divi_turnstile_activate() {
    // Configuration par défaut
    if (!get_option('divi_turnstile_options')) {
        add_option('divi_turnstile_options', array(
            'site_key' => '0x4AAAAAABASNytVIyRKRmt5',
            'secret_key' => '0x4AAAAAABASN31xWED_HJuuFX3jtJF0MZ0',
            'enable_pages' => 'contact,nous-contacter'
        ));
    }
}
register_activation_hook(__FILE__, 'divi_turnstile_activate');

/**
 * Fonction de désactivation du plugin
 */
function divi_turnstile_deactivate() {
    // Ne pas supprimer les options pour conserver la configuration
}
register_deactivation_hook(__FILE__, 'divi_turnstile_deactivate');

/**
 * Fonction de désinstallation du plugin
 */
function divi_turnstile_uninstall() {
    // Supprimer les options du plugin
    delete_option('divi_turnstile_options');
}
register_uninstall_hook(__FILE__, 'divi_turnstile_uninstall');

// Lancer le plugin
function divi_turnstile_init() {
    return Divi_Turnstile_Integration::get_instance();
}
add_action('plugins_loaded', 'divi_turnstile_init');