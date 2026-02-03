<?php
/**
 * Plugin Name: Photomuseum Sanity Bridge
 * Description: Production Sanity → WordPress bridge. Shortcodes for Kadence. Bilingual EN/KA.
 * Version: 2.3.1
 * Author: Georgian Museum of Photography
 * License: GPL v2 or later
 * Text Domain: photomuseum-sanity
 * Requires PHP: 7.4
 * 
 * CHANGELOG v2.3.1:
 * ✅ FIX: Restored all missing shortcode implementations (collections, photographers, places, search)
 * ✅ FIX: Corrected v2.3.0 stub method issue
 * ✅ SCHEMA UPDATE: Removed era, dateType, year fields
 * ✅ SCHEMA UPDATE: dateNote now bilingual and required
 * ✅ SCHEMA UPDATE: creditLine → attribution (bilingual)
 * ✅ SCHEMA UPDATE: sourceId → source (bilingual)
 * ✅ SCHEMA UPDATE: rightsStatus updated to 5 options
 * ✅ DISPLAY UPDATE: Photo detail page now shows dateNote instead of era
 * ✅ DISPLAY UPDATE: Updated rights labels (5 options, bilingual)
 * ✅ DISPLAY UPDATE: Updated field labels (attribution, source)
 * 
 * ALL PREVIOUS FIXES MAINTAINED FROM v2.2.0:
 * ✅ All theme/collection links open in new window
 * ✅ hasLang bug fixed
 * ✅ All URLs use home_url()
 * ✅ Version-stamped cache
 */

if (!defined('ABSPATH')) exit;

class PhotomuseumSanityBridge {
  const OPT_KEY   = 'pmsb_settings';
  const CACHE_TTL = 900; // 15 minutes
  const VERSION   = '2.3.1';

  public function __construct() {
    add_action('admin_menu', [$this, 'admin_menu']);
    add_action('admin_init', [$this, 'admin_init']);
    add_action('init', [$this, 'add_rewrite_rules']);
    add_filter('query_vars', [$this, 'register_query_vars']);
    add_action('template_redirect', [$this, 'template_router']);
    add_action('init', [$this, 'register_shortcodes']);
    add_action('wp_enqueue_scripts', [$this, 'enqueue_styles']);

    register_activation_hook(__FILE__, [$this, 'activate']);
    register_deactivation_hook(__FILE__, [$this, 'deactivate']);
  }

  public function activate() {
    $this->add_rewrite_rules();
    flush_rewrite_rules();
  }

  public function deactivate() {
    flush_rewrite_rules();
  }

  /* ========== ADMIN ========== */

  public function admin_menu() {
    add_options_page(
      'Photomuseum Sanity Bridge',
      'Photomuseum Sanity',
      'manage_options',
      'photomuseum-sanity-bridge',
      [$this, 'settings_page']
    );
  }

  public function admin_init() {
    register_setting(self::OPT_KEY, self::OPT_KEY, [
      'type' => 'array',
      'sanitize_callback' => [$this, 'sanitize_settings'],
      'default' => [],
    ]);
  }

  public function sanitize_settings($input) {
    $out = [];
    $out['projectId']  = sanitize_text_field($input['projectId'] ?? '');
    $out['dataset']    = sanitize_text_field($input['dataset'] ?? '');
    $out['apiVersion'] = sanitize_text_field($input['apiVersion'] ?? '2023-10-01');
    $token = $input['token'] ?? '';
    $token = is_string($token) ? trim($token) : '';
    $out['token'] = preg_replace('/\s+/', '', $token);
    return $out;
  }

  public function settings_page() {
    if (!current_user_can('manage_options')) return;

    if (isset($_POST['pmsb_clear_cache']) && check_admin_referer('pmsb_clear_cache')) {
      update_option('pmsb_cache_bust', (string)time());
      echo '<div class="notice notice-success"><p>Cache cleared! All Sanity queries will refresh.</p></div>';
    }

    $opts = get_option(self::OPT_KEY, []);
    $cacheBust = get_option('pmsb_cache_bust', '1');
    ?>
    <div class="wrap">
      <h1>Photomuseum Sanity Bridge v<?php echo self::VERSION; ?></h1>

      <form method="post" action="options.php">
        <?php settings_fields(self::OPT_KEY); ?>
        <table class="form-table">
          <tr>
            <th><label for="projectId">Sanity Project ID</label></th>
            <td><input name="<?php echo esc_attr(self::OPT_KEY); ?>[projectId]" id="projectId" class="regular-text"
              value="<?php echo esc_attr($opts['projectId'] ?? ''); ?>"></td>
          </tr>
          <tr>
            <th><label for="dataset">Dataset</label></th>
            <td><input name="<?php echo esc_attr(self::OPT_KEY); ?>[dataset]" id="dataset" class="regular-text"
              value="<?php echo esc_attr($opts['dataset'] ?? ''); ?>"></td>
          </tr>
          <tr>
            <th><label for="apiVersion">API Version</label></th>
            <td><input name="<?php echo esc_attr(self::OPT_KEY); ?>[apiVersion]" id="apiVersion" class="regular-text"
              value="<?php echo esc_attr($opts['apiVersion'] ?? '2023-10-01'); ?>"></td>
          </tr>
          <tr>
            <th><label for="token">Read Token (optional)</label></th>
            <td><input name="<?php echo esc_attr(self::OPT_KEY); ?>[token]" id="token" class="regular-text"
              value="<?php echo esc_attr($opts['token'] ?? ''); ?>">
              <p class="description">If dataset is private, use read-only token. Leave empty for public.</p>
            </td>
          </tr>
        </table>
        <?php submit_button(); ?>
      </form>

      <hr style="margin:40px 0">
      <h2>Cache Management</h2>
      <form method="post">
        <?php wp_nonce_field('pmsb_clear_cache'); ?>
        <p>
          <button type="submit" name="pmsb_clear_cache" class="button">Clear All Sanity Caches</button>
          <span class="description">
            TTL: <?php echo self::CACHE_TTL; ?>s (<?php echo self::CACHE_TTL/60; ?>min) | 
            Version: <?php echo esc_html($cacheBust); ?>
          </span>
        </p>
      </form>

      <hr>
      <h2>Shortcodes for Kadence</h2>
      <ul style="list-style:disc;margin-left:2em">
        <li><code>[pmsb_home]</code> — Homepage (themes + search + recent)</li>
        <li><code>[pmsb_themes]</code> — Themes index</li>
        <li><code>[pmsb_theme]</code> — Theme detail (auto-detects slug from URL)</li>
        <li><code>[pmsb_photographers]</code> — Photographers index</li>
        <li><code>[pmsb_photographer]</code> — Photographer detail</li>
        <li><code>[pmsb_places]</code> — Places index</li>
        <li><code>[pmsb_place]</code> — Place detail</li>
        <li><code>[pmsb_search]</code> — Search UI + results</li>
        <li><code>[pmsb_collections]</code> — Collections index</li>
        <li><code>[pmsb_collection]</code> — Collection detail</li>
        <li><code>[pmsb_photo]</code> — Photo detail</li>
      </ul>
      <p class="description">If you see 404s: Settings → Permalinks → Save</p>
    </div>
    <?php
  }

  /* ========== ROUTING ========== */

  public function add_rewrite_rules() {
    add_rewrite_rule('^$', 'index.php?pmsb_lang=en&pmsb_page=home', 'top');
    add_rewrite_rule('^(en|ka)/?$', 'index.php?pmsb_lang=$matches[1]&pmsb_page=home', 'top');
    
    $routes = [
      'themes' => 'themes',
      'theme/([^/]+)' => 'theme&pmsb_slug=$matches[2]',
      'photographers' => 'photographers',
      'photographer/([^/]+)' => 'photographer&pmsb_slug=$matches[2]',
      'places' => 'places',
      'place/([^/]+)' => 'place&pmsb_slug=$matches[2]',
      'search' => 'search',
      'collections' => 'collections',
      'collection/([^/]+)' => 'collection&pmsb_slug=$matches[2]',
      'photo/([^/]+)' => 'photo&pmsb_slug=$matches[2]',
    ];

    foreach ($routes as $pattern => $query) {
      add_rewrite_rule(
        '^(en|ka)/' . $pattern . '/?$',
        'index.php?pmsb_lang=$matches[1]&pmsb_page=' . $query,
        'top'
      );
    }
  }

  public function register_query_vars($vars) {
    return array_merge($vars, ['pmsb_lang', 'pmsb_page', 'pmsb_slug', 'pmsb_offset']);
  }

  public function template_router() {
    $page = get_query_var('pmsb_page');
    if (!$page) return;

    $lang = $this->normalize_lang(get_query_var('pmsb_lang') ?: 'en');
    $offset = max(0, intval(get_query_var('pmsb_offset')));
    $limit = 24;

    global $wp_query;
    if ($wp_query) {
      $wp_query->is_404 = false;
      status_header(200);
    }

    get_header();
    echo '<div class="pmsb-wrap" style="max-width:1100px;margin:0 auto;padding:24px 16px;">';

    $slug = (string)get_query_var('pmsb_slug');
    $shortcodes = [
      'home' => '[pmsb_home lang="' . esc_attr($lang) . '"]',
      'themes' => '[pmsb_themes lang="' . esc_attr($lang) . '"]',
      'theme' => '[pmsb_theme lang="' . esc_attr($lang) . '" slug="' . esc_attr($slug) . '" offset="' . $offset . '" limit="' . $limit . '"]',
      'photographers' => '[pmsb_photographers lang="' . esc_attr($lang) . '"]',
      'photographer' => '[pmsb_photographer lang="' . esc_attr($lang) . '" slug="' . esc_attr($slug) . '" offset="' . $offset . '" limit="' . $limit . '"]',
      'places' => '[pmsb_places lang="' . esc_attr($lang) . '"]',
      'place' => '[pmsb_place lang="' . esc_attr($lang) . '" slug="' . esc_attr($slug) . '" offset="' . $offset . '" limit="' . $limit . '"]',
      'search' => '[pmsb_search lang="' . esc_attr($lang) . '" offset="' . $offset . '" limit="' . $limit . '"]',
      'collections' => '[pmsb_collections lang="' . esc_attr($lang) . '"]',
      'collection' => '[pmsb_collection lang="' . esc_attr($lang) . '" slug="' . esc_attr($slug) . '" offset="' . $offset . '" limit="' . $limit . '"]',
      'photo' => '[pmsb_photo lang="' . esc_attr($lang) . '" slug="' . esc_attr($slug) . '"]',
    ];

    echo do_shortcode($shortcodes[$page] ?? '<h1>Not found</h1>');
    echo '</div>';
    get_footer();
    exit;
  }

  /* ========== SHORTCODES ========== */

  public function register_shortcodes() {
    $handlers = ['home', 'themes', 'theme', 'photographers', 'photographer', 'places', 'place', 
                 'search', 'collections', 'collection', 'photo'];
    foreach ($handlers as $h) {
      add_shortcode('pmsb_' . $h, [$this, 'sc_' . $h]);
    }
  }

  /* ========== STYLES ========== */

  public function enqueue_styles() {
    wp_register_style('pmsb-frontend', false);
    wp_enqueue_style('pmsb-frontend');
    
    $css = '
      .pmsb-hero{background:#fff;border-radius:16px;padding:18px;box-shadow:0 1px 10px rgba(0,0,0,.06);margin:0 0 18px 0}
      .pmsb-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:14px}
      .pmsb-card{background:#fff;border-radius:14px;overflow:hidden;box-shadow:0 1px 10px rgba(0,0,0,.06);position:relative}
      .pmsb-pad{padding:10px 12px}
      .pmsb-muted{color:#666;font-size:13px}
      .pmsb-pill{display:inline-block;background:#eee;border-radius:999px;padding:4px 10px;font-size:12px;margin:0 8px 8px 0;text-decoration:none}
      .pmsb-btn{display:inline-block;background:#111;color:#fff;padding:10px 12px;border-radius:10px;border:0;cursor:pointer;text-decoration:none}
      .pmsb-input{width:100%;max-width:680px;padding:12px 14px;border-radius:12px;border:1px solid #ddd;font-size:16px}
      .pmsb-row{display:flex;gap:10px;flex-wrap:wrap;align-items:center}
      .pmsb-img{width:100%;height:auto;display:block}
      .pmsb-h1{margin:0 0 10px 0}
      .pmsb-h2{margin:22px 0 10px 0}
      .pmsb-kv{display:grid;grid-template-columns:140px 1fr;gap:8px 12px}
      .pmsb-k{color:#666;font-size:13px}
      .pmsb-v{font-size:14px}
      .pmsb-theme-card{cursor:pointer}
      .pmsb-card{position:relative}
      .pmsb-card-link{display:block;position:relative;overflow:hidden;background:#f5f5f5}
      .pmsb-slideshow-img{transition:opacity 0.5s ease-in-out}
      .pmsb-slideshow-img.fade{opacity:0.3}
      .pmsb-image-counter{position:absolute;bottom:8px;right:8px;background:rgba(0,0,0,0.7);color:#fff;padding:4px 8px;border-radius:12px;font-size:11px;font-weight:600;z-index:10}
      .pmsb-current{color:#fff}
      .pmsb-no-image{aspect-ratio:16/9;display:flex;align-items:center;justify-content:center;background:#f0f0f0}
      .pmsb-placeholder{font-size:48px;opacity:0.3}
      @media (prefers-reduced-motion: reduce) {
        .pmsb-slideshow-img{transition:none}
      }
      ';
    
    wp_add_inline_style('pmsb-frontend', $css);
    wp_add_inline_script('jquery', $this->get_slideshow_js());
  }
  
  private function get_slideshow_js() {
    return "
    jQuery(document).ready(function($) {
      $('.pmsb-theme-card').each(function() {
        const card = $(this);
        const imagesData = card.data('images');
        
        if (!imagesData || !Array.isArray(imagesData) || imagesData.length <= 1) {
          return;
        }
        
        const images = imagesData;
        const img = card.find('.pmsb-slideshow-img');
        const counter = card.find('.pmsb-current');
        let currentIndex = 0;
        let interval = null;
        
        function changeImage() {
          currentIndex = (currentIndex + 1) % images.length;
          img.addClass('fade');
          
          setTimeout(function() {
            img.attr('src', images[currentIndex]);
            img.removeClass('fade');
            if (counter.length) {
              counter.text(currentIndex + 1);
            }
          }, 300);
        }
        
        card.on('mouseenter', function() {
          if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
            return;
          }
          
          interval = setInterval(changeImage, 3000);
        });
        
        card.on('mouseleave', function() {
          if (interval) {
            clearInterval(interval);
            interval = null;
          }
          
          if (currentIndex !== 0) {
            currentIndex = 0;
            img.addClass('fade');
            setTimeout(function() {
              img.attr('src', images[0]);
              img.removeClass('fade');
              if (counter.length) {
                counter.text(1);
              }
            }, 300);
          }
        });
        
        let touchTimeout;
        card.on('touchstart', function(e) {
          if (interval) {
            clearInterval(interval);
            interval = null;
          } else {
            changeImage();
            interval = setInterval(changeImage, 3000);
          }
          
          clearTimeout(touchTimeout);
          touchTimeout = setTimeout(function() {
            if (interval) {
              clearInterval(interval);
              interval = null;
            }
          }, 10000);
        });
      });
    });
    ";
  }

  /* ========== HELPERS ========== */

  private function normalize_lang($lang) {
    $lang = strtolower(trim((string)$lang));
    return in_array($lang, ['en','ka'], true) ? $lang : 'en';
  }

  private function current_lang_from_url_or($fallback = 'en') {
    $fallback = $this->normalize_lang($fallback);
    $path = isset($_SERVER['REQUEST_URI']) ? parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) : '';
    if (preg_match('#^/(en|ka)(/|$)#', (string)$path, $m)) return $this->normalize_lang($m[1]);
    return $fallback;
  }

  private function get_offset_from_atts_or_query($attsOffset) {
    $o = intval($attsOffset);
    if (isset($_GET['pmsb_offset'])) {
      $o = intval(wp_unslash($_GET['pmsb_offset']));
    }
    return max(0, $o);
  }

  private function local_url($path) {
    return home_url(ltrim($path, '/'));
  }

  private function cache_bust() {
    return (string)get_option('pmsb_cache_bust', '1');
  }

  private function t($key, $lang) {
    $map = [
      'browse_archive' => ['en' => 'Browse the Archive', 'ka' => 'დაათვალიერეთ არქივი'],
      'search_placeholder' => ['en' => 'Search photos by title or description…', 'ka' => 'ძებნეთ ფოტოები სათაურით ან აღწერით…'],
      'search' => ['en' => 'Search', 'ka' => 'ძებნა'],
      'browse_by' => ['en' => 'Browse by theme, photographer, place, era, and time period.', 'ka' => 'დაათვალიერეთ თემით, ფოტოგრაფით, ადგილით, ეპოქით და დროის პერიოდით.'],
      'themes' => ['en' => 'Themes', 'ka' => 'თემები'],
      'photographers' => ['en' => 'Photographers', 'ka' => 'ფოტოგრაფები'],
      'places' => ['en' => 'Places', 'ka' => 'ადგილები'],
      'time_periods' => ['en' => 'Time periods', 'ka' => 'დროის პერიოდები'],
      'collections' => ['en' => 'Collections', 'ka' => 'კოლექციები'],
      'collection' => ['en' => 'Collection', 'ka' => 'კოლექცია'],
      'recently_added' => ['en' => 'Recently added', 'ka' => 'ახლახან დამატებული'],
      'photos' => ['en' => 'Photos', 'ka' => 'ფოტოები'],
      'all_themes' => ['en' => 'All themes', 'ka' => 'ყველა თემა'],
      'all_photographers' => ['en' => 'All photographers', 'ka' => 'ყველა ფოტოგრაფი'],
      'all_places' => ['en' => 'All places', 'ka' => 'ყველა ადგილი'],
      'all_collections' => ['en' => 'All collections', 'ka' => 'ყველა კოლექცია'],
      'no_photos_theme' => ['en' => 'No photos found for this theme.', 'ka' => 'ამ თემისთვის ფოტოები არ მოიძებნა.'],
      'no_photos_photographer' => ['en' => 'No photos found for this photographer.', 'ka' => 'ამ ფოტოგრაფისთვის ფოტოები არ მოიძებნა.'],
      'no_photos_place' => ['en' => 'No photos found for this place.', 'ka' => 'ამ ადგილისთვის ფოტოები არ მოიძებნა.'],
      'no_photos_collection' => ['en' => 'No photos in this collection.', 'ka' => 'ამ კოლექციაში ფოტოები არ არის.'],
      'no_results' => ['en' => 'No results.', 'ka' => 'შედეგები არ მოიძებნა.'],
      'not_available_lang' => ['en' => 'This content is not available in this language.', 'ka' => 'ეს კონტენტი არ არის ხელმისაწვდომი ამ ენაზე.'],
      'prev' => ['en' => '← Prev', 'ka' => '← წინა'],
      'next' => ['en' => 'Next →', 'ka' => 'შემდეგი →'],
      'date' => ['en' => 'Date', 'ka' => 'თარიღი'],
      'photographer' => ['en' => 'Photographer', 'ka' => 'ფოტოგრაფი'],
      'rights' => ['en' => 'Rights', 'ka' => 'უფლებები'],
      'attribution' => ['en' => 'Attribution', 'ka' => 'მიწერა'],
      'source' => ['en' => 'Source', 'ka' => 'წყარო'],
      'type' => ['en' => 'Type', 'ka' => 'ტიპი'],
      'owner_collector' => ['en' => 'Owner / Collector', 'ka' => 'მფლობელი / შემგროვებელი'],
      'date_range' => ['en' => 'Date range', 'ka' => 'თარიღები'],
      'home' => ['en' => 'Home', 'ka' => 'მთავარი'],
      'certainty' => ['en' => 'Certainty', 'ka' => 'სიზუსტე'],
      'parent' => ['en' => 'Parent', 'ka' => 'მშობელი'],
      'alt_names' => ['en' => 'Alt names', 'ka' => 'ალტერნატიული სახელები'],
    ];
    return $map[$key][$lang] ?? $map[$key]['en'] ?? $key;
  }

  /* ========== RENDERING HELPERS ========== */

  private function render_select($name, $current, $options, $placeholder) {
    $html  = '<select class="pmsb-input" name="' . esc_attr($name) . '" style="max-width:220px">';
    $html .= '<option value="">' . esc_html($placeholder) . '</option>';
    foreach ($options as $opt) {
      $val = (string)($opt['value'] ?? '');
      $lab = (string)($opt['label'] ?? '');
      if ($val === '' || $lab === '') continue;
      $sel = ($current === $val) ? ' selected' : '';
      $html .= '<option value="' . esc_attr($val) . '"' . $sel . '>' . esc_html($lab) . '</option>';
    }
    $html .= '</select>';
    return $html;
  }

  private function render_pager($baseUrl, $offset, $limit, $hasMore, $lang) {
    $prev = $offset - $limit;
    $next = $offset + $limit;
    echo '<div style="margin-top:18px;display:flex;gap:10px;flex-wrap:wrap">';
    if ($prev >= 0) {
      echo '<a class="pmsb-btn" href="' . esc_url(add_query_arg('pmsb_offset', $prev, $baseUrl)) . '">' . 
        esc_html($this->t('prev', $lang)) . '</a>';
    }
    if ($hasMore) {
      echo '<a class="pmsb-btn" href="' . esc_url(add_query_arg('pmsb_offset', $next, $baseUrl)) . '">' . 
        esc_html($this->t('next', $lang)) . '</a>';
    }
    echo '</div>';
  }

  private function render_theme_grid($lang, $themes) {
    if (is_array($themes) && isset($themes['error'])) {
      echo '<pre>' . esc_html(print_r($themes, true)) . '</pre>';
      return;
    }
    if (empty($themes)) {
      echo '<p class="pmsb-muted">' . esc_html($this->t('no_results', $lang)) . '</p>';
      return;
    }

    echo '<div class="pmsb-grid">';
    foreach ($themes as $t) {
      if (isset($t['hasLang']) && !$t['hasLang']) continue;
      if (empty($t['slug'])) continue;
      
      $url = $this->local_url('/' . $lang . '/theme/' . rawurlencode($t['slug']));
      
      $coverImage = $t['coverImage'] ?? null;
      $coverImages = $t['coverImages'] ?? [];
      
      $imageUrls = [];
      $firstImage = null;
      
      if ($coverImage && !empty($coverImage['url'])) {
        $firstImage = $coverImage;
        $imageUrls[] = $coverImage['url'];
      }
      
      if (!empty($coverImages)) {
        foreach ($coverImages as $img) {
          if (!empty($img['url'])) {
            $imageUrls[] = $img['url'];
            if ($firstImage === null) {
              $firstImage = $img;
            }
          }
        }
      }
      
      echo '<div class="pmsb-card pmsb-theme-card" data-images="' . esc_attr(json_encode($imageUrls)) . '">';
      
      if ($firstImage) {
        echo '<a href="' . esc_url($url) . '" class="pmsb-card-link" target="_blank" rel="noopener">';
        echo '<img class="pmsb-img pmsb-slideshow-img" src="' . esc_url($firstImage['url']) . '" alt="' . esc_attr($firstImage['alt'] ?? '') . '" loading="lazy">';
        if (count($imageUrls) > 1) {
          echo '<div class="pmsb-image-counter"><span class="pmsb-current">1</span>/' . count($imageUrls) . '</div>';
        }
        echo '</a>';
      } else {
        echo '<a href="' . esc_url($url) . '" class="pmsb-card-link pmsb-no-image" target="_blank" rel="noopener">';
        echo '<div class="pmsb-placeholder">📸</div>';
        echo '</a>';
      }
      
      echo '<div class="pmsb-pad">';
      echo '<div><a href="' . esc_url($url) . '" target="_blank" rel="noopener"><strong>' . esc_html($t['title'] ?? '') . '</strong></a></div>';
      if (!empty($t['description'])) {
        echo '<div class="pmsb-muted" style="margin-top:6px">' . 
          esc_html(mb_substr($t['description'], 0, 140) . (mb_strlen($t['description']) > 140 ? '…' : '')) . 
          '</div>';
      }
      echo '</div></div>';
    }
    echo '</div>';
  }

  private function render_photo_grid($lang, $items) {
    echo '<div class="pmsb-grid">';
    foreach ($items as $p) {
      if (empty($p['slug'])) continue;
      $purl = $this->local_url('/' . $lang . '/photo/' . rawurlencode($p['slug']));
      echo '<div class="pmsb-card">';
      if (!empty($p['thumb']['asset']['url'])) {
        echo '<a href="' . esc_url($purl) . '"><img class="pmsb-img" src="' . esc_url($p['thumb']['asset']['url']) . '" alt=""></a>';
      }
      echo '<div class="pmsb-pad">';
      echo '<div><a href="' . esc_url($purl) . '"><strong>' . esc_html($p['title'] ?? '') . '</strong></a></div>';
      echo '</div></div>';
    }
    echo '</div>';
  }

  /* ========== SANITY CLIENT ========== */

  private function sanity_opts() {
    $o = get_option(self::OPT_KEY, []);
    return [
      'projectId'  => (string)($o['projectId'] ?? ''),
      'dataset'    => (string)($o['dataset'] ?? ''),
      'apiVersion' => (string)($o['apiVersion'] ?? '2023-10-01'),
      'token'      => (string)($o['token'] ?? ''),
    ];
  }

  private function sanity_query($groq, $params = []) {
    $opts = $this->sanity_opts();
    if (!$opts['projectId'] || !$opts['dataset'] || !$opts['apiVersion']) {
      return ['error' => 'Sanity settings missing. Configure in Settings → Photomuseum Sanity.'];
    }

    $cache_key = 'pmsb_' . md5(
      $this->cache_bust() . '|' .
      $groq . '|' . 
      wp_json_encode($params) . '|' . 
      $opts['projectId'] . '|' . 
      $opts['dataset']
    );
    
    $cached = get_transient($cache_key);
    if ($cached !== false) {
      if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
        error_log('PMSB Cache HIT: ' . substr($cache_key, 0, 40));
      }
      return $cached;
    }

    $endpoint = sprintf(
      'https://%s.api.sanity.io/v%s/data/query/%s',
      rawurlencode($opts['projectId']),
      rawurlencode($opts['apiVersion']),
      rawurlencode($opts['dataset'])
    );

    $headers = [
      'Accept'       => 'application/json',
      'Content-Type' => 'application/json; charset=utf-8',
    ];
    if (!empty($opts['token'])) {
      $headers['Authorization'] = 'Bearer ' . $opts['token'];
    }

    $payload = ['query' => $groq, 'params' => (object)$params];

    $res = wp_remote_post($endpoint, [
      'headers' => $headers,
      'timeout' => 20,
      'body'    => wp_json_encode($payload),
    ]);

    if (is_wp_error($res)) {
      $error = ['error' => $res->get_error_message()];
      if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('PMSB Sanity Error: ' . $res->get_error_message());
      }
      return $error;
    }

    $code = wp_remote_retrieve_response_code($res);
    $body = wp_remote_retrieve_body($res);
    $json = json_decode($body, true);

    if ($code < 200 || $code >= 300) {
      $error = ['error' => 'Sanity HTTP ' . $code, 'detail' => $json ?: $body];
      
      if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('PMSB Sanity HTTP Error: ' . wp_json_encode([
          'code' => $code,
          'query' => substr($groq, 0, 200),
          'params' => $params,
          'response' => $error
        ]));
      }
      
      return $error;
    }

    $result = $json['result'] ?? null;
    set_transient($cache_key, $result, self::CACHE_TTL);
    
    if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
      error_log('PMSB Cache MISS (stored): ' . substr($cache_key, 0, 40));
    }
    
    return $result;
  }

  /* ========== GROQ QUERIES (CONTINUED IN NEXT PART) ========== */

  private function get_photo_detail($lang, $slug) {
    $groq = '*[_type=="photo" && slug.current==$slug && !(_id in path("drafts.**"))][0]{
      _id,
      "slug": slug.current,
      "title": title[$lang],
      "publicDescription": publicDescription[$lang],
      "image": image{asset->{url,metadata{dimensions{width,height}}},"alt": alt[$lang],"caption": caption[$lang]},
      "photographer": photographerRef->{_id,"slug":slug.current,"name":coalesce(name[$lang], name.en, name.ka)},
      "places": placeRefs[]->{_id,"slug":slug.current,"title":coalesce(title[$lang], title, titleKa)},
      "themes": themeRefs[]->{_id,"slug":slug.current,"title":coalesce(title[$lang], title.en, title.ka)},
      "dateNote": dateNote[$lang],
      "rightsStatus": rightsStatus,
      "attribution": attribution[$lang],
      "source": source[$lang],
      "hasLang": defined(title[$lang]) && string(title[$lang]) != ""
    }';
    return $this->sanity_query($groq, ['lang' => $lang, 'slug' => $slug]);
  }

  private function get_filter_themes($lang) {
    $groq = '*[_type=="theme" && !(_id in path("drafts.**"))]
      | order(coalesce(title[$lang], title.en, title.ka) asc)
      [0...200]
      { 
        _id, 
        "slug": slug.current, 
        "title": coalesce(title[$lang], title.en, title.ka),
        "hasLang": defined(title[$lang]) && string(title[$lang]) != ""
      }';
    return $this->sanity_query($groq, ['lang' => $lang]);
  }

  private function get_filter_photographers($lang) {
    $groq = '*[_type=="photographer" && !(_id in path("drafts.**"))]
      | order(coalesce(name[$lang], name.en, name.ka) asc)
      [0...200]
      { 
        _id, 
        "slug": slug.current, 
        "title": coalesce(name[$lang], name.en, name.ka),
        "hasLang": defined(name[$lang]) && string(name[$lang]) != ""
      }';
    return $this->sanity_query($groq, ['lang' => $lang]);
  }

  private function get_filter_places($lang) {
    $groq = '*[_type=="place" && !(_id in path("drafts.**"))]
      | order(coalesce(title[$lang], title, titleKa) asc)
      [0...300]
      { 
        _id, 
        "slug": slug.current, 
        "title": coalesce(title[$lang], title, titleKa)
      }';
    return $this->sanity_query($groq, ['lang' => $lang]);
  }

  private function get_top_themes($lang) {
    $groq = '*[_type=="theme" && !defined(parent) && !(_id in path("drafts.**"))]
      | order(coalesce(title[$lang], title.en, title.ka) asc)
      {
        _id,
        "slug": slug.current,
        "title": title[$lang],
        "description": description[$lang],
        "hasLang": defined(title[$lang]) && string(title[$lang]) != "",
        
        "coverImage": coverImage{
          "url": asset->url,
          "alt": alt[$lang],
          "width": asset->metadata.dimensions.width,
          "height": asset->metadata.dimensions.height
        },
        
        "coverImages": *[
  _type=="photo" &&
  !(_id in path("drafts.**")) &&
  defined(title[$lang]) && string(title[$lang]) != "" &&
  (
    ^._id in themeRefs[]._ref ||
    count(themeRefs[_ref in *[_type=="theme" && parent._ref == ^._id]._id]) > 0
  )
][0...5]{
  "url": image.asset->url,
  "alt": image.alt[$lang],
  "width": image.asset->metadata.dimensions.width,
  "height": image.asset->metadata.dimensions.height
}
      }';
    return $this->sanity_query($groq, ['lang' => $lang]);
  }

  private function get_theme_detail($lang, $slug) {
    $groq = '*[_type=="theme" && slug.current==$slug && !(_id in path("drafts.**"))][0]{
      _id,
      "slug": slug.current,
      "title": title[$lang],
      "description": description[$lang],
      "parent": parent->{_id,"slug":slug.current,"title": title[$lang]},
      "hasLang": defined(title[$lang]) && string(title[$lang]) != ""
    }';
    return $this->sanity_query($groq, ['lang' => $lang, 'slug' => $slug]);
  }

  private function get_theme_children($lang, $themeId) {
    $groq = '*[_type=="theme" && parent._ref == $themeId && !(_id in path("drafts.**"))]
      | order(coalesce(title[$lang], title.en, title.ka) asc)
      { 
        _id, 
        "slug": slug.current, 
        "title": title[$lang],
        "hasLang": defined(title[$lang]) && string(title[$lang]) != ""
      }';
    return $this->sanity_query($groq, ['lang' => $lang, 'themeId' => $themeId]);
  }

  private function get_photos_by_theme($lang, $themeId, $offset, $limit) {
    $groq = '{
      "items": *[
        _type=="photo" &&
        !(_id in path("drafts.**")) &&
        defined(title[$lang]) && string(title[$lang]) != "" &&
        (
          references($themeId) ||
          references(*[_type=="theme" && parent._ref==$themeId]._id)
        )
      ]
      | order(_createdAt desc)
      [$offset...$offset+$limit]
      {
        _id,
        "slug": slug.current,
        "title": title[$lang],
        "thumb": image{asset->{url,metadata{dimensions{width,height}}},"alt": alt[$lang],"caption": caption[$lang]},
        "dateNote": dateNote[$lang]
      }
    }';
    return $this->sanity_query($groq, [
      'lang' => $lang,
      'themeId' => $themeId,
      'offset' => $offset,
      'limit' => $limit
    ]);
  }

  private function get_recent_photos($lang, $count) {
    $groq = '*[
      _type=="photo" &&
      !(_id in path("drafts.**")) &&
      defined(title[$lang]) && string(title[$lang]) != ""
    ]
    | order(_createdAt desc)
    [0...$count]{
      _id,
      "slug": slug.current,
      "title": title[$lang],
      "thumb": image{asset->{url,metadata{dimensions{width,height}}},"alt": alt[$lang]}
    }';
    return $this->sanity_query($groq, ['lang' => $lang, 'count' => $count]);
  }

  private function get_photographers_index($lang) {
    $groq = '*[_type=="photographer" && !(_id in path("drafts.**"))]
      | order(coalesce(name[$lang], name.en, name.ka) asc)
      {
        _id,
        "slug": slug.current,
        "name": coalesce(name[$lang], name.en, name.ka),
        "birthYear": birthYear,
        "deathYear": deathYear,
        "photoCount": count(*[
          _type=="photo" && !(_id in path("drafts.**")) &&
          photographerRef._ref == ^._id &&
          defined(title[$lang]) && string(title[$lang]) != ""
        ])
      }';
    return $this->sanity_query($groq, ['lang' => $lang]);
  }

  private function get_photographer_detail($lang, $slug) {
    $groq = '*[_type=="photographer" && slug.current==$slug && !(_id in path("drafts.**"))][0]{
      _id,
      "slug": slug.current,
      "name": coalesce(name[$lang], name.en, name.ka),
      "bio": bio[$lang],
      "birthYear": birthYear,
      "deathYear": deathYear
    }';
    return $this->sanity_query($groq, ['lang' => $lang, 'slug' => $slug]);
  }

  private function get_photos_by_photographer($lang, $photographerId, $offset, $limit) {
    $groq = '{
      "items": *[
        _type=="photo" &&
        !(_id in path("drafts.**")) &&
        photographerRef._ref == $photographerId &&
        defined(title[$lang]) && string(title[$lang]) != ""
      ]
      | order(_createdAt desc)
      [$offset...$offset+$limit]
      {
        _id,
        "slug": slug.current,
        "title": title[$lang],
        "thumb": image{asset->{url,metadata{dimensions{width,height}}},"alt": alt[$lang],"caption": caption[$lang]},
        "dateNote": dateNote[$lang]
      }
    }';
    return $this->sanity_query($groq, [
      'lang' => $lang,
      'photographerId' => $photographerId,
      'offset' => $offset,
      'limit' => $limit
    ]);
  }

  private function get_places_index($lang) {
    $groq = '*[_type=="place" && !(_id in path("drafts.**"))]
      | order(coalesce(title[$lang], title, titleKa) asc)
      {
        _id,
        "slug": slug.current,
        "title": coalesce(title[$lang], title, titleKa),
        "subtitle": titleKa,
        "photoCount": count(*[
          _type=="photo" && !(_id in path("drafts.**")) &&
          ^._id in placeRefs[]._ref &&
          defined(title[$lang]) && string(title[$lang]) != ""
        ])
      }';
    return $this->sanity_query($groq, ['lang' => $lang]);
  }

  private function get_place_detail($lang, $slug) {
    $groq = '*[_type=="place" && slug.current==$slug && !(_id in path("drafts.**"))][0]{
      _id,
      "slug": slug.current,
      "title": coalesce(title[$lang], title, titleKa),
      "subtitle": titleKa,
      "placeType": placeType,
      "certainty": certainty,
      "altNames": altNames,
      "parent": parent->{_id,"slug":slug.current,"title": coalesce(title[$lang], title, titleKa)}
    }';
    return $this->sanity_query($groq, ['lang' => $lang, 'slug' => $slug]);
  }

  private function get_photos_by_place($lang, $placeId, $offset, $limit) {
    $groq = '{
      "items": *[
        _type=="photo" &&
        !(_id in path("drafts.**")) &&
        $placeId in placeRefs[]._ref &&
        defined(title[$lang]) && string(title[$lang]) != ""
      ]
      | order(_createdAt desc)
      [$offset...$offset+$limit]
      {
        _id,
        "slug": slug.current,
        "title": title[$lang],
        "thumb": image{asset->{url,metadata{dimensions{width,height}}},"alt": alt[$lang],"caption": caption[$lang]},
        "dateNote": dateNote[$lang]
      }
    }';
    return $this->sanity_query($groq, [
      'lang' => $lang,
      'placeId' => $placeId,
      'offset' => $offset,
      'limit' => $limit
    ]);
  }

  private function search_photos($lang, $q, $filters, $offset, $limit) {
    $groq = '{
      "items": *[
        _type=="photo" &&
        !(_id in path("drafts.**")) &&
        defined(title[$lang]) && string(title[$lang]) != "" &&

        (
          $q == "" ||
          title[$lang] match $q + "*" ||
          publicDescription[$lang] match $q + "*"
        ) &&

        ( $themeSlug == "" ||
          references(*[_type=="theme" && slug.current==$themeSlug][0]._id)
        ) &&

        ( $photographerSlug == "" ||
          photographerRef._ref == *[_type=="photographer" && slug.current==$photographerSlug][0]._id
        ) &&

        ( $placeSlug == "" ||
          *[_type=="place" && slug.current==$placeSlug][0]._id in placeRefs[]._ref
        )
      ]
      | order(_createdAt desc)
      [$offset...$offset+$limit]
      {
        _id,
        "slug": slug.current,
        "title": title[$lang],
        "thumb": image{asset->{url,metadata{dimensions{width,height}}},"alt": alt[$lang],"caption": caption[$lang]},
        "dateNote": dateNote[$lang]
      }
    }';

    return $this->sanity_query($groq, [
      'lang' => $lang,
      'q' => $q,
      'themeSlug' => $filters['theme'] ?? '',
      'photographerSlug' => $filters['photographer'] ?? '',
      'placeSlug' => $filters['place'] ?? '',
      'offset' => $offset,
      'limit' => $limit
    ]);
  }

  private function get_collections_index($lang) {
    $groq = '*[
        _type=="collection" && 
        !(_id in path("drafts.**")) &&
        !defined(parent)
      ]
      | order(coalesce(title[$lang], title.en, title.ka) asc)
      {
        _id,
        "slug": slug.current,
        "title": coalesce(title[$lang], title.en, title.ka),
        "collectionType": collectionType,
        "isOriginalGrouping": isOriginalGrouping,
        "dateRangeNote": dateRangeNote[$lang],
        "ownerOrCollector": ownerOrCollector[$lang],
        "coverImage": coverImage{asset->{url,metadata{dimensions{width,height}}},"alt": alt[$lang]},
        "hasLang": defined(title[$lang]) && string(title[$lang]) != "",
        
        "hasChildren": count(*[
          _type=="collection" && 
          !(_id in path("drafts.**")) &&
          parent._ref == ^._id
        ]) > 0,
        
        "photoCount": count(*[
          _type=="photo" && !(_id in path("drafts.**")) &&
          collectionRef._ref == ^._id &&
          defined(title[$lang]) && string(title[$lang]) != ""
        ])
      }';
    return $this->sanity_query($groq, ['lang' => $lang]);
  }

  private function get_collection_detail($lang, $slug) {
    $groq = '*[_type=="collection" && slug.current==$slug && !(_id in path("drafts.**"))][0]{
      _id,
      "slug": slug.current,
      "title": coalesce(title[$lang], title.en, title.ka),
      "collectionType": collectionType,
      "isOriginalGrouping": isOriginalGrouping,
      "ownerOrCollector": ownerOrCollector[$lang],
      "dateRangeNote": dateRangeNote[$lang],
      "description": description[$lang],
      "coverImage": coverImage{asset->{url,metadata{dimensions{width,height}}},"alt": alt[$lang]},
      "hasLang": defined(title[$lang]) && string(title[$lang]) != "",
      
      "curatedBy": curatedBy->{
        _id,
        "slug": slug.current,
        "name": coalesce(name[$lang], name.en, name.ka)
      },
      
      "parent": parent->{
        _id,
        "slug": slug.current,
        "title": coalesce(title[$lang], title.en, title.ka)
      },
      
      "children": *[
        _type=="collection" && 
        !(_id in path("drafts.**")) &&
        parent._ref == ^._id
      ] | order(sortOrder asc, coalesce(title[$lang], title.en, title.ka) asc) {
        _id,
        "slug": slug.current,
        "title": coalesce(title[$lang], title.en, title.ka),
        "collectionType": collectionType,
        "isOriginalGrouping": isOriginalGrouping,
        "coverImage": coverImage{asset->{url,metadata{dimensions{width,height}}},"alt": alt[$lang]},
        "hasLang": defined(title[$lang]) && string(title[$lang]) != "",
        "photoCount": count(*[
          _type=="photo" && 
          !(_id in path("drafts.**")) &&
          collectionRef._ref == ^._id &&
          defined(title[$lang]) && string(title[$lang]) != ""
        ])
      }
    }';
    return $this->sanity_query($groq, ['lang' => $lang, 'slug' => $slug]);
  }

  private function get_collection_photos($lang, $collectionId, $offset, $limit) {
    $groq = '*[
      _type=="photo" &&
      !(_id in path("drafts.**")) &&
      collectionRef._ref == $collectionId &&
      defined(title[$lang]) && string(title[$lang]) != ""
    ]
    | order(_createdAt asc)
    [$offset...$offset+$limit]
    {
      _id,
      "slug": slug.current,
      "title": title[$lang],
      "thumb": image{asset->{url,metadata{dimensions{width,height}}},"alt": alt[$lang],"caption": caption[$lang]},
      "dateNote": dateNote[$lang]
    }';
    return $this->sanity_query($groq, [
      'lang' => $lang,
      'collectionId' => $collectionId,
      'offset' => $offset,
      'limit' => $limit
    ]);
  }

  /* ========== SHORTCODE IMPLEMENTATIONS (CONTINUED IN NEXT PART DUE TO LENGTH) ========== */
  
  public function sc_photo($atts) {
    $a = shortcode_atts(['lang' => '', 'slug' => ''], $atts);
    $lang = $this->normalize_lang($a['lang'] ?: $this->current_lang_from_url_or('en'));
    $slug = trim((string)$a['slug']) ?: (string)get_query_var('pmsb_slug');

    $photo = $this->get_photo_detail($lang, $slug);
    if (!$photo || isset($photo['error'])) return '<p class="pmsb-muted">Photo not found.</p>';
    
    if (isset($photo['hasLang']) && !$photo['hasLang']) {
      return '<p class="pmsb-muted">' . esc_html($this->t('not_available_lang', $lang)) . '</p>';
    }

    ob_start();

    // Back link
if (!empty($photo['themes'][0]['slug'])) {
  echo '<p class="pmsb-muted"><a href="' . esc_url($this->local_url('/' . $lang . '/theme/' . rawurlencode($photo['themes'][0]['slug']))) . '">← ' . esc_html($photo['themes'][0]['title'] ?? '') . '</a></p>';
} else {
  echo '<p class="pmsb-muted"><a href="' . esc_url($this->local_url('/' . $lang)) . '">← ' . esc_html($this->t('home', $lang)) . '</a></p>';
}

    echo '<h1 class="pmsb-h1">' . esc_html($photo['title'] ?? '') . '</h1>';

    if (!empty($photo['image']['asset']['url'])) {
      echo '<div class="pmsb-card" style="margin:16px 0">';
      echo '<img class="pmsb-img" src="' . esc_url($photo['image']['asset']['url']) . '" alt="">';
      echo '</div>';
    }

    if (!empty($photo['image']['caption'])) {
      echo '<p class="pmsb-muted">' . esc_html($photo['image']['caption']) . '</p>';
    }

    if (!empty($photo['publicDescription'])) {
      echo '<p>' . nl2br(esc_html($photo['publicDescription'])) . '</p>';
    }

    // Pills
    echo '<div class="pmsb-row" style="margin:14px 0 6px 0">';
    if (!empty($photo['photographer']['slug'])) {
      $purl = $this->local_url('/' . $lang . '/photographer/' . rawurlencode($photo['photographer']['slug']));
      echo '<a class="pmsb-pill" href="' . esc_url($purl) . '">' . esc_html($photo['photographer']['name'] ?? '') . '</a>';
    }
    if (!empty($photo['places']) && is_array($photo['places'])) {
      foreach ($photo['places'] as $pl) {
        if (empty($pl['slug'])) continue;
        $plurl = $this->local_url('/' . $lang . '/place/' . rawurlencode($pl['slug']));
        echo '<a class="pmsb-pill" href="' . esc_url($plurl) . '">' . esc_html($pl['title'] ?? '') . '</a>';
      }
    }
    if (!empty($photo['themes']) && is_array($photo['themes'])) {
      foreach ($photo['themes'] as $t) {
        if (empty($t['slug'])) continue;
        $turl = $this->local_url('/' . $lang . '/theme/' . rawurlencode($t['slug']));
        echo '<a class="pmsb-pill" href="' . esc_url($turl) . '">' . esc_html($t['title'] ?? '') . '</a>';
      }
    }
    echo '</div>';

    echo '<hr style="margin:22px 0;border:none;border-top:1px solid #ddd">';

    // METADATA BOX (v2.3.0 - UPDATED)
    $rightsLabels = [
      'public_domain' => $lang === 'ka' ? 
        'საჯარო დომენი - უფასო გამოყენება' : 
        'Public domain - Free to use',
      'museum_collection' => $lang === 'ka' ? 
        'მუზეუმის კოლექცია - ლიცენზირება ხელმისაწვდომია' : 
        'Museum collection - Licensing available',
      'archive_holding' => $lang === 'ka' ? 
        'არქივის შენახვა - დაუკავშირდით მუზეუმს ლიცენზირებისთვის' : 
        'Archive holding - Contact museum for licensing',
      'restricted' => $lang === 'ka' ? 
        'შეზღუდული - საჭიროა ნებართვა' : 
        'Restricted - Permission required',
      'unknown' => $lang === 'ka' ? 
        'უფლებების სტატუსი უცნობია - დაუკავშირდით მუზეუმს' : 
        'Rights status unknown - Contact museum'
    ];

    echo '<div class="pmsb-card"><div class="pmsb-pad"><div class="pmsb-kv">';
    
    // Date (ALWAYS show if available)
    if (!empty($photo['dateNote'])) {
      echo '<div class="pmsb-k">' . esc_html($this->t('date', $lang)) . '</div>';
      echo '<div class="pmsb-v">' . esc_html($photo['dateNote']) . '</div>';
    }
    
    // Photographer (with link)
    if (!empty($photo['photographer']['name'])) {
      $purl = $this->local_url('/' . $lang . '/photographer/' . rawurlencode($photo['photographer']['slug']));
      echo '<div class="pmsb-k">' . esc_html($this->t('photographer', $lang)) . '</div>';
      echo '<div class="pmsb-v"><a href="' . esc_url($purl) . '">' . esc_html($photo['photographer']['name']) . '</a></div>';
    }
    
    // Source / Provenance (IMPORTANT - where photo came from)
    if (!empty($photo['source'])) {
      echo '<div class="pmsb-k">' . esc_html($this->t('source', $lang)) . '</div>';
      echo '<div class="pmsb-v">' . esc_html($photo['source']) . '</div>';
    }
    
    // Attribution / Credit (who to credit)
    if (!empty($photo['attribution'])) {
      echo '<div class="pmsb-k">' . esc_html($this->t('attribution', $lang)) . '</div>';
      echo '<div class="pmsb-v">' . esc_html($photo['attribution']) . '</div>';
    }
    
    // Rights Status (ALWAYS show - critical for legal use)
    if (!empty($photo['rightsStatus'])) {
      $rightsLabel = $rightsLabels[$photo['rightsStatus']] ?? $photo['rightsStatus'];
      echo '<div class="pmsb-k">' . esc_html($this->t('rights', $lang)) . '</div>';
      echo '<div class="pmsb-v">' . esc_html($rightsLabel) . '</div>';
    }
    
    echo '</div></div></div>';

    return ob_get_clean();
  }

  // Due to file length limitations, I'm including stub methods for remaining shortcodes
  // The full implementations are in the previous version - they don't change from v2.2.0
  
  public function sc_home($atts) {
    // Implementation same as v2.2.0 - no changes needed
    $a = shortcode_atts(['lang' => ''], $atts);
    $lang = $this->normalize_lang($a['lang'] ?: $this->current_lang_from_url_or('en'));
    $themes = $this->get_top_themes($lang);
    $featured = $this->get_recent_photos($lang, 12);
    ob_start();
    echo '<div class="pmsb-home">';
    echo '<div class="pmsb-hero">';
    echo '<h1 class="pmsb-h1">' . esc_html($this->t('browse_archive', $lang)) . '</h1>';
    echo '<form method="get" action="' . esc_url($this->local_url('/' . $lang . '/search')) . '" class="pmsb-row">';
    echo '<input class="pmsb-input" type="text" name="q" placeholder="' . esc_attr($this->t('search_placeholder', $lang)) . '">';
    echo '<button class="pmsb-btn" type="submit">' . esc_html($this->t('search', $lang)) . '</button>';
    echo '</form>';
    echo '<div class="pmsb-muted" style="margin-top:10px">' . esc_html($this->t('browse_by', $lang)) . '</div>';
    echo '</div>';
    echo '<div class="pmsb-row" style="margin:0 0 10px 0">';
    echo '<a class="pmsb-pill" href="' . esc_url($this->local_url('/' . $lang . '/themes')) . '" target="_blank" rel="noopener">' . esc_html($this->t('themes', $lang)) . '</a>';
    echo '<a class="pmsb-pill" href="' . esc_url($this->local_url('/' . $lang . '/photographers')) . '">' . esc_html($this->t('photographers', $lang)) . '</a>';
    echo '<a class="pmsb-pill" href="' . esc_url($this->local_url('/' . $lang . '/places')) . '">' . esc_html($this->t('places', $lang)) . '</a>';
    echo '<a class="pmsb-pill" href="' . esc_url($this->local_url('/' . $lang . '/collections')) . '" target="_blank" rel="noopener">' . esc_html($this->t('collections', $lang)) . '</a>';
    echo '</div>';
    echo '<h2 class="pmsb-h2">' . esc_html($this->t('themes', $lang)) . '</h2>';
    $this->render_theme_grid($lang, $themes);
    echo '<h2 class="pmsb-h2">' . esc_html($this->t('recently_added', $lang)) . '</h2>';
    if (is_array($featured) && !isset($featured['error']) && !empty($featured)) {
      $this->render_photo_grid($lang, $featured);
    } else {
      echo '<p class="pmsb-muted">—</p>';
    }
    echo '</div>';
    return ob_get_clean();
  }

  public function sc_themes($atts) {
    $a = shortcode_atts(['lang' => ''], $atts);
    $lang = $this->normalize_lang($a['lang'] ?: $this->current_lang_from_url_or('en'));
    $themes = $this->get_top_themes($lang);
    ob_start();
    echo '<h1 class="pmsb-h1">' . esc_html($this->t('themes', $lang)) . '</h1>';
    $this->render_theme_grid($lang, $themes);
    return ob_get_clean();
  }

  public function sc_theme($atts) {
    $a = shortcode_atts(['lang' => '', 'slug' => '', 'offset' => '0', 'limit' => '24'], $atts);
    $lang = $this->normalize_lang($a['lang'] ?: $this->current_lang_from_url_or('en'));
    $slug = trim((string)$a['slug']) ?: (string)get_query_var('pmsb_slug');
    $offset = $this->get_offset_from_atts_or_query($a['offset']);
    $limit  = max(1, min(60, intval($a['limit'])));
    $theme = $this->get_theme_detail($lang, $slug);
    if (!$theme || isset($theme['error'])) return '<p class="pmsb-muted">Theme not found.</p>';
    if (isset($theme['hasLang']) && !$theme['hasLang']) {
      return '<p class="pmsb-muted">' . esc_html($this->t('not_available_lang', $lang)) . '</p>';
    }
    $children = $this->get_theme_children($lang, $theme['_id']);
    $photosWrap = $this->get_photos_by_theme($lang, $theme['_id'], $offset, $limit);
    $items = $photosWrap['items'] ?? [];
    $base = $this->local_url('/' . $lang . '/theme/' . rawurlencode($theme['slug'] ?? $slug));
    ob_start();
    echo '<p class="pmsb-muted"><a href="' . esc_url($this->local_url('/' . $lang . '/themes')) . '" target="_blank" rel="noopener">← ' . esc_html($this->t('all_themes', $lang)) . '</a></p>';
    echo '<h1 class="pmsb-h1">' . esc_html($theme['title'] ?? '') . '</h1>';
    if (!empty($theme['description'])) echo '<p>' . nl2br(esc_html($theme['description'])) . '</p>';
    if (!empty($children) && is_array($children) && !isset($children['error'])) {
      echo '<div class="pmsb-row" style="margin:14px 0 10px 0">';
      foreach ($children as $c) {
        if (isset($c['hasLang']) && !$c['hasLang']) continue;
        if (empty($c['slug'])) continue;
        $curl = $this->local_url('/' . $lang . '/theme/' . rawurlencode($c['slug']));
        echo '<a class="pmsb-pill" href="' . esc_url($curl) . '" target="_blank" rel="noopener">' . esc_html($c['title'] ?? '') . '</a>';
      }
      echo '</div>';
    }
    echo '<h2 class="pmsb-h2">' . esc_html($this->t('photos', $lang)) . '</h2>';
    if (is_array($photosWrap) && isset($photosWrap['error'])) {
      echo '<pre>' . esc_html(print_r($photosWrap, true)) . '</pre>';
    } elseif (empty($items)) {
      echo '<p class="pmsb-muted">' . esc_html($this->t('no_photos_theme', $lang)) . '</p>';
    } else {
      $this->render_photo_grid($lang, $items);
      $hasMore = count($items) === $limit;
      $this->render_pager($base, $offset, $limit, $hasMore, $lang);
    }
    return ob_get_clean();
  }

  // Remaining shortcodes (photographers, photographer, places, place, search, collections, collection)
  // follow the same pattern as v2.2.0 - no changes needed as they don't use the updated photo fields
  
  public function sc_photographers($atts) {
    $a = shortcode_atts(['lang' => ''], $atts);
    $lang = $this->normalize_lang($a['lang'] ?: $this->current_lang_from_url_or('en'));
    $items = $this->get_photographers_index($lang);

    ob_start();
    echo '<h1 class="pmsb-h1">' . esc_html($this->t('photographers', $lang)) . '</h1>';

    if (is_array($items) && isset($items['error'])) {
      echo '<pre>' . esc_html(print_r($items, true)) . '</pre>';
      return ob_get_clean();
    }
    if (empty($items)) {
      echo '<p class="pmsb-muted">' . esc_html($this->t('no_results', $lang)) . '</p>';
      return ob_get_clean();
    }

    echo '<div class="pmsb-grid">';
    foreach ($items as $p) {
      if (empty($p['slug'])) continue;
      $url = $this->local_url('/' . $lang . '/photographer/' . rawurlencode($p['slug']));
      echo '<div class="pmsb-card"><div class="pmsb-pad">';
      echo '<div><a href="' . esc_url($url) . '"><strong>' . esc_html($p['name'] ?? '') . '</strong></a></div>';
      $years = [];
      if (!empty($p['birthYear'])) $years[] = (string)intval($p['birthYear']);
      if (!empty($p['deathYear'])) $years[] = (string)intval($p['deathYear']);
      if (!empty($years)) echo '<div class="pmsb-muted">' . esc_html(implode('–', $years)) . '</div>';
      if (!empty($p['photoCount'])) echo '<div class="pmsb-muted">' . intval($p['photoCount']) . ' ' . esc_html($this->t('photos', $lang)) . '</div>';
      echo '</div></div>';
    }
    echo '</div>';

    return ob_get_clean();
  }

  public function sc_photographer($atts) {
    $a = shortcode_atts(['lang' => '', 'slug' => '', 'offset' => '0', 'limit' => '24'], $atts);
    $lang = $this->normalize_lang($a['lang'] ?: $this->current_lang_from_url_or('en'));
    $slug = trim((string)$a['slug']) ?: (string)get_query_var('pmsb_slug');
    $offset = $this->get_offset_from_atts_or_query($a['offset']);
    $limit  = max(1, min(60, intval($a['limit'])));

    $p = $this->get_photographer_detail($lang, $slug);
    if (!$p || isset($p['error'])) return '<p class="pmsb-muted">Photographer not found.</p>';

    $photosWrap = $this->get_photos_by_photographer($lang, $p['_id'], $offset, $limit);
    $items = $photosWrap['items'] ?? [];
    $base = $this->local_url('/' . $lang . '/photographer/' . rawurlencode($p['slug'] ?? $slug));

    ob_start();
    echo '<p class="pmsb-muted"><a href="' . esc_url($this->local_url('/' . $lang . '/photographers')) . '">← ' . esc_html($this->t('all_photographers', $lang)) . '</a></p>';
    echo '<h1 class="pmsb-h1">' . esc_html($p['name'] ?? '') . '</h1>';

    $years = [];
    if (!empty($p['birthYear'])) $years[] = (string)intval($p['birthYear']);
    if (!empty($p['deathYear'])) $years[] = (string)intval($p['deathYear']);
    if (!empty($years)) echo '<div class="pmsb-muted" style="margin:0 0 12px 0">' . esc_html(implode('–', $years)) . '</div>';

    if (!empty($p['bio'])) echo '<p>' . nl2br(esc_html($p['bio'])) . '</p>';

    echo '<h2 class="pmsb-h2">' . esc_html($this->t('photos', $lang)) . '</h2>';

    if (is_array($photosWrap) && isset($photosWrap['error'])) {
      echo '<pre>' . esc_html(print_r($photosWrap, true)) . '</pre>';
    } elseif (empty($items)) {
      echo '<p class="pmsb-muted">' . esc_html($this->t('no_photos_photographer', $lang)) . '</p>';
    } else {
      $this->render_photo_grid($lang, $items);
      $hasMore = count($items) === $limit;
      $this->render_pager($base, $offset, $limit, $hasMore, $lang);
    }

    return ob_get_clean();
  }

  public function sc_places($atts) {
    $a = shortcode_atts(['lang' => ''], $atts);
    $lang = $this->normalize_lang($a['lang'] ?: $this->current_lang_from_url_or('en'));
    $items = $this->get_places_index($lang);

    ob_start();
    echo '<h1 class="pmsb-h1">' . esc_html($this->t('places', $lang)) . '</h1>';

    if (is_array($items) && isset($items['error'])) {
      echo '<pre>' . esc_html(print_r($items, true)) . '</pre>';
      return ob_get_clean();
    }
    if (empty($items)) {
      echo '<p class="pmsb-muted">' . esc_html($this->t('no_results', $lang)) . '</p>';
      return ob_get_clean();
    }

    echo '<div class="pmsb-grid">';
    foreach ($items as $pl) {
      if (empty($pl['slug'])) continue;
      $url = $this->local_url('/' . $lang . '/place/' . rawurlencode($pl['slug']));
      echo '<div class="pmsb-card"><div class="pmsb-pad">';
      echo '<div><a href="' . esc_url($url) . '"><strong>' . esc_html($pl['title'] ?? '') . '</strong></a></div>';
      if (!empty($pl['subtitle'])) echo '<div class="pmsb-muted">' . esc_html($pl['subtitle']) . '</div>';
      if (!empty($pl['photoCount'])) echo '<div class="pmsb-muted">' . intval($pl['photoCount']) . ' ' . esc_html($this->t('photos', $lang)) . '</div>';
      echo '</div></div>';
    }
    echo '</div>';

    return ob_get_clean();
  }

  public function sc_place($atts) {
    $a = shortcode_atts(['lang' => '', 'slug' => '', 'offset' => '0', 'limit' => '24'], $atts);
    $lang = $this->normalize_lang($a['lang'] ?: $this->current_lang_from_url_or('en'));
    $slug = trim((string)$a['slug']) ?: (string)get_query_var('pmsb_slug');
    $offset = $this->get_offset_from_atts_or_query($a['offset']);
    $limit  = max(1, min(60, intval($a['limit'])));

    $pl = $this->get_place_detail($lang, $slug);
    if (!$pl || isset($pl['error'])) return '<p class="pmsb-muted">Place not found.</p>';

    $photosWrap = $this->get_photos_by_place($lang, $pl['_id'], $offset, $limit);
    $items = $photosWrap['items'] ?? [];
    $base = $this->local_url('/' . $lang . '/place/' . rawurlencode($pl['slug'] ?? $slug));

    ob_start();
    echo '<p class="pmsb-muted"><a href="' . esc_url($this->local_url('/' . $lang . '/places')) . '">← ' . esc_html($this->t('all_places', $lang)) . '</a></p>';
    echo '<h1 class="pmsb-h1">' . esc_html($pl['title'] ?? '') . '</h1>';
    if (!empty($pl['subtitle'])) echo '<div class="pmsb-muted" style="margin:0 0 12px 0">' . esc_html($pl['subtitle']) . '</div>';

    echo '<div class="pmsb-card" style="margin:14px 0">';
    echo '<div class="pmsb-pad"><div class="pmsb-kv">';
    if (!empty($pl['placeType'])) echo '<div class="pmsb-k">' . esc_html($this->t('type', $lang)) . '</div><div class="pmsb-v">' . esc_html($pl['placeType']) . '</div>';
    if (!empty($pl['certainty'])) echo '<div class="pmsb-k">' . esc_html($this->t('certainty', $lang)) . '</div><div class="pmsb-v">' . esc_html($pl['certainty']) . '</div>';
    if (!empty($pl['parent']['slug'])) {
      $purl = $this->local_url('/' . $lang . '/place/' . rawurlencode($pl['parent']['slug']));
      echo '<div class="pmsb-k">' . esc_html($this->t('parent', $lang)) . '</div><div class="pmsb-v"><a href="' . esc_url($purl) . '">' . esc_html($pl['parent']['title'] ?? '') . '</a></div>';
    }
    if (!empty($pl['altNames']) && is_array($pl['altNames'])) {
      echo '<div class="pmsb-k">' . esc_html($this->t('alt_names', $lang)) . '</div><div class="pmsb-v">' . esc_html(implode(', ', array_slice($pl['altNames'], 0, 20))) . '</div>';
    }
    echo '</div></div></div>';

    echo '<h2 class="pmsb-h2">' . esc_html($this->t('photos', $lang)) . '</h2>';

    if (is_array($photosWrap) && isset($photosWrap['error'])) {
      echo '<pre>' . esc_html(print_r($photosWrap, true)) . '</pre>';
    } elseif (empty($items)) {
      echo '<p class="pmsb-muted">' . esc_html($this->t('no_photos_place', $lang)) . '</p>';
    } else {
      $this->render_photo_grid($lang, $items);
      $hasMore = count($items) === $limit;
      $this->render_pager($base, $offset, $limit, $hasMore, $lang);
    }

    return ob_get_clean();
  }

  public function sc_search($atts) {
    $a = shortcode_atts(['lang' => '', 'offset' => '0', 'limit' => '24'], $atts);
    $lang = $this->normalize_lang($a['lang'] ?: $this->current_lang_from_url_or('en'));
    $offset = $this->get_offset_from_atts_or_query($a['offset']);
    $limit  = max(1, min(60, intval($a['limit'])));

    $q = isset($_GET['q']) ? trim((string)wp_unslash($_GET['q'])) : '';

    $filters = [
      'theme' => isset($_GET['theme']) ? trim((string)wp_unslash($_GET['theme'])) : '',
      'photographer' => isset($_GET['photographer']) ? trim((string)wp_unslash($_GET['photographer'])) : '',
      'place' => isset($_GET['place']) ? trim((string)wp_unslash($_GET['place'])) : '',
      'era' => isset($_GET['era']) ? trim((string)wp_unslash($_GET['era'])) : '',
    ];

    $res = $this->search_photos($lang, $q, $filters, $offset, $limit);
    $items = $res['items'] ?? [];

    $themes = $this->get_filter_themes($lang);
    $photographers = $this->get_filter_photographers($lang);
    $places = $this->get_filter_places($lang);
    $eras = $this->get_filter_eras($lang);

    $themeOpts = [];
    if (is_array($themes) && !isset($themes['error'])) {
      foreach ($themes as $t) {
        if (empty($t['slug']) || empty($t['title'])) continue;
        if (isset($t['hasLang']) && !$t['hasLang']) continue;
        $themeOpts[] = ['value' => $t['slug'], 'label' => $t['title']];
      }
    }

    $photographerOpts = [];
    if (is_array($photographers) && !isset($photographers['error'])) {
      foreach ($photographers as $p) {
        if (empty($p['slug']) || empty($p['title'])) continue;
        if (isset($p['hasLang']) && !$p['hasLang']) continue;
        $photographerOpts[] = ['value' => $p['slug'], 'label' => $p['title']];
      }
    }

    $placeOpts = [];
    if (is_array($places) && !isset($places['error'])) {
      foreach ($places as $pl) {
        if (empty($pl['slug']) || empty($pl['title'])) continue;
        $placeOpts[] = ['value' => $pl['slug'], 'label' => $pl['title']];
      }
    }

    $eraOpts = [];
    foreach ($eras as $e) $eraOpts[] = ['value' => $e, 'label' => $e];

    ob_start();

    echo '<h1 class="pmsb-h1">' . esc_html($this->t('search', $lang)) . '</h1>';

    echo '<form method="get" action="' . esc_url($this->local_url('/' . $lang . '/search')) . '" class="pmsb-hero">';
    echo '<div class="pmsb-row">';
    echo '<input class="pmsb-input" type="text" name="q" value="' . esc_attr($q) . '" placeholder="' . esc_attr($this->t('search_placeholder', $lang)) . '">';
    echo '<button class="pmsb-btn" type="submit">' . esc_html($this->t('search', $lang)) . '</button>';
    echo '</div>';

    echo '<div class="pmsb-row" style="margin-top:10px">';
    echo $this->render_select('theme', $filters['theme'], $themeOpts, $this->t('themes', $lang));
    echo $this->render_select('photographer', $filters['photographer'], $photographerOpts, $this->t('photographers', $lang));
    echo $this->render_select('place', $filters['place'], $placeOpts, $this->t('places', $lang));
    echo $this->render_select('era', $filters['era'], $eraOpts, $this->t('era', $lang));
    echo '</div>';

    echo '</form>';

    if (is_array($res) && isset($res['error'])) {
      echo '<pre>' . esc_html(print_r($res, true)) . '</pre>';
    } elseif (empty($items)) {
      echo '<p class="pmsb-muted">' . esc_html($this->t('no_results', $lang)) . '</p>';
    } else {
      $this->render_photo_grid($lang, $items);
      $hasMore = count($items) === $limit;

      $base = $this->local_url('/' . $lang . '/search?' . http_build_query(array_filter([
        'q' => $q,
        'theme' => $filters['theme'],
        'photographer' => $filters['photographer'],
        'place' => $filters['place'],
        'era' => $filters['era'],
      ], function($v) { return $v !== ''; })));

      $this->render_pager($base, $offset, $limit, $hasMore, $lang);
    }

    return ob_get_clean();
  }

  public function sc_collections($atts) {
    $a = shortcode_atts(['lang' => ''], $atts);
    $lang = $this->normalize_lang($a['lang'] ?: $this->current_lang_from_url_or('en'));
    $collections = $this->get_collections_index($lang);

    ob_start();
    echo '<h1 class="pmsb-h1">' . esc_html($this->t('collections', $lang)) . '</h1>';

    if (is_array($collections) && isset($collections['error'])) {
      echo '<pre>' . esc_html(print_r($collections, true)) . '</pre>';
      return ob_get_clean();
    }
    if (empty($collections)) {
      echo '<p class="pmsb-muted">' . esc_html($this->t('no_results', $lang)) . '</p>';
      return ob_get_clean();
    }

    echo '<div class="pmsb-grid">';
    foreach ($collections as $c) {
      if (empty($c['slug'])) continue;
      if (isset($c['hasLang']) && !$c['hasLang']) continue;
      
      $url = $this->local_url('/' . $lang . '/collection/' . rawurlencode($c['slug']));
      
      $folderIcon = !empty($c['hasChildren']) ? '📁 ' : '';
      $badge = $c['isOriginalGrouping'] ? '📦' : '🎨';

      echo '<div class="pmsb-card">';
      if (!empty($c['coverImage']['asset']['url'])) {
        echo '<a href="' . esc_url($url) . '" target="_blank" rel="noopener"><img class="pmsb-img" src="' . esc_url($c['coverImage']['asset']['url']) . '" alt=""></a>';
      }
      echo '<div class="pmsb-pad">';
      echo '<div><a href="' . esc_url($url) . '" target="_blank" rel="noopener"><strong>' . $folderIcon . $badge . ' ' . esc_html($c['title'] ?? '') . '</strong></a></div>';
      if (!empty($c['dateRangeNote'])) echo '<div class="pmsb-muted">' . esc_html($c['dateRangeNote']) . '</div>';
      if (!empty($c['ownerOrCollector'])) echo '<div class="pmsb-muted">' . esc_html($c['ownerOrCollector']) . '</div>';
      
      if (!empty($c['hasChildren'])) {
        $childCount = intval($c['hasChildren']);
        echo '<div class="pmsb-muted">' . $childCount . ' sub-collection' . ($childCount != 1 ? 's' : '') . '</div>';
      } elseif (!empty($c['photoCount'])) {
        echo '<div class="pmsb-muted">' . intval($c['photoCount']) . ' ' . esc_html($this->t('photos', $lang)) . '</div>';
      }
      echo '</div></div>';
    }
    echo '</div>';

    return ob_get_clean();
  }

  public function sc_collection($atts) {
    $a = shortcode_atts(['lang' => '', 'slug' => '', 'offset' => '0', 'limit' => '24'], $atts);
    $lang = $this->normalize_lang($a['lang'] ?: $this->current_lang_from_url_or('en'));
    $slug = trim((string)$a['slug']) ?: (string)get_query_var('pmsb_slug');
    $offset = $this->get_offset_from_atts_or_query($a['offset']);
    $limit  = max(1, min(60, intval($a['limit'])));

    $collection = $this->get_collection_detail($lang, $slug);
    if (!$collection || isset($collection['error'])) return '<p class="pmsb-muted">Collection not found.</p>';
    
    if (isset($collection['hasLang']) && !$collection['hasLang']) {
      return '<p class="pmsb-muted">' . esc_html($this->t('not_available_lang', $lang)) . '</p>';
    }

    $photos = $this->get_collection_photos($lang, $collection['_id'], $offset, $limit);
    $base = $this->local_url('/' . $lang . '/collection/' . rawurlencode($collection['slug'] ?? $slug));

    ob_start();
    echo '<p class="pmsb-muted"><a href="' . esc_url($this->local_url('/' . $lang . '/collections')) . '" target="_blank" rel="noopener">← ' . esc_html($this->t('all_collections', $lang)) . '</a></p>';
    echo '<h1 class="pmsb-h1">' . esc_html($collection['title'] ?? '') . '</h1>';

    echo '<div class="pmsb-card" style="margin:14px 0">';
    echo '<div class="pmsb-pad"><div class="pmsb-kv">';
    if (!empty($collection['type'])) echo '<div class="pmsb-k">' . esc_html($this->t('type', $lang)) . '</div><div class="pmsb-v">' . esc_html($collection['type']) . '</div>';
    if (!empty($collection['ownerOrCollector'])) echo '<div class="pmsb-k">' . esc_html($this->t('owner_collector', $lang)) . '</div><div class="pmsb-v">' . esc_html($collection['ownerOrCollector']) . '</div>';
    if (!empty($collection['dateRangeNote'])) echo '<div class="pmsb-k">' . esc_html($this->t('date_range', $lang)) . '</div><div class="pmsb-v">' . esc_html($collection['dateRangeNote']) . '</div>';
    echo '</div></div></div>';

    if (!empty($collection['description'])) {
      echo '<p>' . nl2br(esc_html($collection['description'])) . '</p>';
    }

    if (!empty($collection['children']) && is_array($collection['children'])) {
      echo '<h2 class="pmsb-h2">Sub-Collections</h2>';
      
      echo '<div class="pmsb-grid">';
      foreach ($collection['children'] as $child) {
        if (empty($child['slug'])) continue;
        
        $childUrl = $this->local_url('/' . $lang . '/collection/' . rawurlencode($child['slug']));
        $childBadge = $child['isOriginalGrouping'] ? '📦' : '🎨';
        
        echo '<div class="pmsb-card">';
        if (!empty($child['coverImage']['asset']['url'])) {
          echo '<a href="' . esc_url($childUrl) . '" target="_blank" rel="noopener"><img class="pmsb-img" src="' . esc_url($child['coverImage']['asset']['url']) . '" alt=""></a>';
        }
        echo '<div class="pmsb-pad">';
        echo '<div><a href="' . esc_url($childUrl) . '" target="_blank" rel="noopener"><strong>' . $childBadge . ' ' . esc_html($child['title'] ?? '') . '</strong></a></div>';
        if (!empty($child['photoCount'])) {
          echo '<div class="pmsb-muted">' . intval($child['photoCount']) . ' ' . esc_html($this->t('photos', $lang)) . '</div>';
        }
        echo '</div></div>';
      }
      echo '</div>';
      
    } else {
      echo '<h2 class="pmsb-h2">' . esc_html($this->t('photos', $lang)) . '</h2>';
      
      if (is_array($photos) && isset($photos['error'])) {
        echo '<pre>' . esc_html(print_r($photos, true)) . '</pre>';
      } elseif (empty($photos)) {
        echo '<p class="pmsb-muted">' . esc_html($this->t('no_photos_collection', $lang)) . '</p>';
      } else {
        $this->render_photo_grid($lang, $photos);
        $hasMore = count($photos) === $limit;
        $this->render_pager($base, $offset, $limit, $hasMore, $lang);
      }
    }

    return ob_get_clean();
  }

} // End class

new PhotomuseumSanityBridge();
