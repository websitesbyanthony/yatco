<?php
/**
 * Single Yacht Vessel Template
 * 
 * This template displays a single yacht vessel from the Yacht CPT.
 * Assumes vessel data has been imported and saved as post meta fields.
 * 
 * META FIELD NAMES EXPECTED:
 * ==========================
 * Identity: yacht_year, yacht_make (or yacht_builder), yacht_model, yacht_category, yacht_sub_category
 * Location: yacht_location_custom_rjc, yacht_location_city, yacht_location_state, yacht_location_country
 * Price: yacht_price, yacht_currency, yacht_price_formatted, yacht_price_on_application
 * Status: yacht_status_text, yacht_agreement_type, yacht_days_on_market
 * Dimensions: yacht_length (or yacht_loa), yacht_length_feet, yacht_length_meters, yacht_hull_material
 * Media: yacht_image_url (main photo), yacht_image_gallery_urls (array), yacht_videos (array)
 * Description: yacht_description (or yacht_short_description)
 * Virtual Tour: yacht_virtual_tour_url
 * Broker: yacht_broker_first_name, yacht_broker_last_name, yacht_broker_phone, yacht_broker_email, yacht_broker_photo_url
 * Company: yacht_company_name, yacht_company_logo_url, yacht_company_address, yacht_company_website, yacht_company_phone, yacht_company_email
 * Builder: yacht_builder_description
 * 
 * If your importer uses different meta field names, update the yacht_meta() calls below.
 * 
 * @package YATCO
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Get WordPress header
get_header();

// Get current post ID
$post_id = get_the_ID();

// Helper function to safely get meta field with fallback
function yacht_meta( $key, $default = '' ) {
    global $post_id;
    $value = get_post_meta( $post_id, $key, true );
    return ! empty( $value ) ? $value : $default;
}

// Helper function to safely output HTML
function yacht_output( $value, $default = '' ) {
    $output = ! empty( $value ) ? $value : $default;
    return esc_html( $output );
}

// Helper function to safely output HTML content (allows HTML tags)
function yacht_output_html( $value, $default = '' ) {
    $output = ! empty( $value ) ? $value : $default;
    return wp_kses_post( $output );
}

// Helper function to check if value exists and is not zero/empty
function yacht_has_value( $value ) {
    return ! empty( $value ) && $value !== '0' && $value !== 0;
}

// Collect all meta fields (assuming importer saved these)
$year_built = yacht_meta( 'yacht_year', '' );
$builder = yacht_meta( 'yacht_make', '' ); // or yacht_builder if you named it that
$model = yacht_meta( 'yacht_model', '' );
$main_category = yacht_meta( 'yacht_category', '' );
$sub_category = yacht_meta( 'yacht_sub_category', '' );
$location_custom = yacht_meta( 'yacht_location_custom_rjc', '' );
$location_city = yacht_meta( 'yacht_location_city', '' );
$location_state = yacht_meta( 'yacht_location_state', '' );
$location_country = yacht_meta( 'yacht_location_country', '' );
$asking_price = yacht_meta( 'yacht_price', '' );
$currency = yacht_meta( 'yacht_currency', 'USD' );
$asking_price_formatted = yacht_meta( 'yacht_price_formatted', '' );
$price_on_application = yacht_meta( 'yacht_price_on_application', false );
$status_text = yacht_meta( 'yacht_status_text', '' );
$agreement_type = yacht_meta( 'yacht_agreement_type', '' );
$days_on_market = yacht_meta( 'yacht_days_on_market', '' );
$loa = yacht_meta( 'yacht_length', '' ); // or yacht_loa
$loa_feet = yacht_meta( 'yacht_length_feet', '' );
$loa_meters = yacht_meta( 'yacht_length_meters', '' );
$hull_material = yacht_meta( 'yacht_hull_material', '' );
$vessel_type = yacht_meta( 'yacht_type', '' );
$main_photo_url = yacht_meta( 'yacht_image_url', '' ); // or yacht_main_photo_url
$virtual_tour_url = yacht_meta( 'yacht_virtual_tour_url', '' );
$description = yacht_meta( 'yacht_description', '' ); // or yacht_short_description

// Gallery images (stored as array of URLs or attachment IDs)
$gallery_images = yacht_meta( 'yacht_image_gallery_urls', array() );
if ( empty( $gallery_images ) ) {
    // Fallback: check if stored as attachment IDs
    $gallery_ids = yacht_meta( 'yacht_images', array() );
    if ( ! empty( $gallery_ids ) && is_array( $gallery_ids ) ) {
        $gallery_images = array();
        foreach ( $gallery_ids as $attach_id ) {
            $img_url = wp_get_attachment_image_url( $attach_id, 'large' );
            if ( $img_url ) {
                $gallery_images[] = array( 'url' => $img_url );
            }
        }
    }
}

// Videos (stored as array)
$videos = yacht_meta( 'yacht_videos', array() );

// Broker information
$broker_first_name = yacht_meta( 'yacht_broker_first_name', '' );
$broker_last_name = yacht_meta( 'yacht_broker_last_name', '' );
$broker_phone = yacht_meta( 'yacht_broker_phone', '' );
$broker_email = yacht_meta( 'yacht_broker_email', '' );
$broker_photo_url = yacht_meta( 'yacht_broker_photo_url', '' );
$company_name = yacht_meta( 'yacht_company_name', '' );
$company_logo_url = yacht_meta( 'yacht_company_logo_url', '' );
$company_address = yacht_meta( 'yacht_company_address', '' );
$company_website = yacht_meta( 'yacht_company_website', '' );
$company_phone = yacht_meta( 'yacht_company_phone', '' );
$company_email = yacht_meta( 'yacht_company_email', '' );

// Build title from available parts
$yacht_title_parts = array();
if ( $year_built ) $yacht_title_parts[] = $year_built;
if ( $builder ) $yacht_title_parts[] = $builder;
if ( $model ) $yacht_title_parts[] = $model;
$yacht_title = ! empty( $yacht_title_parts ) ? implode( ' ', $yacht_title_parts ) : get_the_title();

// Build location string
$location_parts = array();
if ( $location_custom ) {
    $location_display = $location_custom;
} else {
    if ( $location_city ) $location_parts[] = $location_city;
    if ( $location_state ) $location_parts[] = $location_state;
    if ( $location_country ) $location_parts[] = $location_country;
    $location_display = implode( ', ', $location_parts );
}

// Build category string
$category_parts = array();
if ( $main_category ) $category_parts[] = $main_category;
if ( $sub_category ) $category_parts[] = $sub_category;
$category_display = implode( ' / ', $category_parts );

// Format price display
$price_display = '';
if ( $price_on_application || empty( $asking_price ) ) {
    $price_display = $asking_price_formatted ? $asking_price_formatted : 'Price on Application';
} else {
    $price_display = $asking_price_formatted ? $asking_price_formatted : $currency . ' ' . number_format( floatval( $asking_price ), 0 );
}

?>

<article class="yacht-single">

  <!-- HERO SECTION -->
  <section class="yacht-hero">
    <div class="yacht-hero-main">

      <!-- Title: Year + Builder + Model -->
      <h1>
        <?php echo yacht_output( $yacht_title ); ?>
      </h1>

      <!-- Subheading: Category + Location -->
      <p class="yacht-hero-sub">
        <?php 
        $sub_parts = array();
        if ( $category_display ) $sub_parts[] = $category_display;
        if ( $location_display ) $sub_parts[] = $location_display;
        echo yacht_output( implode( ' • ', $sub_parts ) );
        ?>
      </p>

      <!-- Price (handle Price on Application vs numeric) -->
      <p class="yacht-price">
        <?php echo yacht_output( $price_display ); ?>
      </p>

      <!-- Status / badges -->
      <?php if ( $status_text || $agreement_type ) : ?>
      <ul class="yacht-hero-badges">
        <?php if ( $status_text ) : ?>
        <li><?php echo yacht_output( $status_text ); ?></li>
        <?php endif; ?>
        <?php if ( $agreement_type ) : ?>
        <li><?php echo yacht_output( $agreement_type ); ?></li>
        <?php endif; ?>
      </ul>
      <?php endif; ?>

      <!-- CTAs -->
      <div class="yacht-hero-cta">
        <a href="#yacht-enquiry" class="btn-primary">Enquire Now</a>

        <?php if ( $virtual_tour_url ) : ?>
        <a href="<?php echo esc_url( $virtual_tour_url ); ?>" target="_blank" class="btn-secondary">
          View Virtual Tour
        </a>
        <?php endif; ?>

        <?php if ( ! empty( $videos ) && is_array( $videos ) ) : ?>
        <a href="#yacht-video" class="btn-secondary">
          Watch Video
        </a>
        <?php endif; ?>
      </div>
    </div>

    <!-- Hero image (main photo) -->
    <?php if ( $main_photo_url ) : ?>
    <figure class="yacht-hero-image">
      <img
        src="<?php echo esc_url( $main_photo_url ); ?>"
        alt="<?php echo esc_attr( $yacht_title ); ?>"
      >
    </figure>
    <?php endif; ?>
  </section>

  <!-- QUICK SPEC BAR -->
  <section class="yacht-quick-specs">
    <ul>
      <?php if ( $loa ) : ?>
      <li>
        <span class="label">Length</span>
        <span class="value"><?php echo yacht_output( $loa ); ?></span>
      </li>
      <?php endif; ?>
      
      <?php if ( $year_built ) : ?>
      <li>
        <span class="label">Year</span>
        <span class="value"><?php echo yacht_output( $year_built ); ?></span>
      </li>
      <?php endif; ?>
      
      <?php if ( $builder ) : ?>
      <li>
        <span class="label">Builder</span>
        <span class="value"><?php echo yacht_output( $builder ); ?></span>
      </li>
      <?php endif; ?>
      
      <?php if ( $hull_material ) : ?>
      <li>
        <span class="label">Hull Material</span>
        <span class="value"><?php echo yacht_output( $hull_material ); ?></span>
      </li>
      <?php endif; ?>
      
      <?php if ( $location_display ) : ?>
      <li>
        <span class="label">Location</span>
        <span class="value"><?php echo yacht_output( $location_display ); ?></span>
      </li>
      <?php endif; ?>
    </ul>
  </section>

  <!-- OVERVIEW / DESCRIPTION -->
  <?php if ( $description ) : ?>
  <section class="yacht-overview">
    <h2>Overview</h2>
    <div class="yacht-overview-body">
      <?php echo yacht_output_html( $description ); ?>
    </div>
  </section>
  <?php endif; ?>

  <!-- MEDIA SECTION -->
  <section class="yacht-media" id="yacht-media">
    <?php if ( ! empty( $gallery_images ) && is_array( $gallery_images ) ) : ?>
    <h2>Gallery</h2>
    <div class="yacht-gallery">
      <?php foreach ( $gallery_images as $image ) : 
        // Handle array structure: ['url' => '...', 'caption' => '...'] or just URL string
        $img_url = is_array( $image ) ? ( $image['url'] ?? $image['largeImageURL'] ?? '' ) : $image;
        $img_medium = is_array( $image ) ? ( $image['mediumImageURL'] ?? $img_url ) : $img_url;
        $img_caption = is_array( $image ) ? ( $image['caption'] ?? '' ) : '';
        
        if ( empty( $img_url ) ) continue;
      ?>
      <a href="<?php echo esc_url( $img_url ); ?>" class="yacht-gallery-item" data-lightbox="yacht-gallery">
        <img
          src="<?php echo esc_url( $img_medium ?: $img_url ); ?>"
          alt="<?php echo esc_attr( $img_caption ?: $yacht_title . ' image' ); ?>"
        >
      </a>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Optional video block -->
    <?php if ( ! empty( $videos ) && is_array( $videos ) ) : 
      $first_video = is_array( $videos[0] ) ? ( $videos[0]['VideoUrl'] ?? $videos[0]['url'] ?? '' ) : $videos[0];
    ?>
    <div class="yacht-video" id="yacht-video">
      <h3>Video</h3>
      <div class="yacht-video-embed">
        <?php if ( $first_video ) : ?>
        <iframe
          src="<?php echo esc_url( $first_video ); ?>"
          frameborder="0"
          allowfullscreen
        ></iframe>
        <?php endif; ?>
      </div>
    </div>
    <?php endif; ?>
  </section>

  <!-- SPECIFICATIONS (simple table / definition list) -->
  <section class="yacht-specs">
    <h2>Specifications</h2>
    <div class="yacht-specs-grid">

      <?php if ( $loa || $loa_feet || $loa_meters ) : ?>
      <div class="yacht-spec-group">
        <h3>Dimensions</h3>
        <dl>
          <?php if ( $loa ) : ?>
          <div>
            <dt>Length (LOA)</dt>
            <dd><?php echo yacht_output( $loa ); ?></dd>
          </div>
          <?php endif; ?>
          <!-- Add Beam, Draft, etc. when available -->
        </dl>
      </div>
      <?php endif; ?>

      <div class="yacht-spec-group">
        <h3>Construction</h3>
        <dl>
          <?php if ( $builder ) : ?>
          <div>
            <dt>Builder</dt>
            <dd><?php echo yacht_output( $builder ); ?></dd>
          </div>
          <?php endif; ?>
          
          <?php if ( $model ) : ?>
          <div>
            <dt>Model</dt>
            <dd><?php echo yacht_output( $model ); ?></dd>
          </div>
          <?php endif; ?>
          
          <?php if ( $year_built ) : ?>
          <div>
            <dt>Year Built</dt>
            <dd><?php echo yacht_output( $year_built ); ?></dd>
          </div>
          <?php endif; ?>
          
          <?php if ( $vessel_type ) : ?>
          <div>
            <dt>Vessel Type</dt>
            <dd><?php echo yacht_output( $vessel_type ); ?></dd>
          </div>
          <?php endif; ?>
          
          <?php if ( $category_display ) : ?>
          <div>
            <dt>Category</dt>
            <dd><?php echo yacht_output( $category_display ); ?></dd>
          </div>
          <?php endif; ?>
          
          <?php if ( $hull_material ) : ?>
          <div>
            <dt>Hull Material</dt>
            <dd><?php echo yacht_output( $hull_material ); ?></dd>
          </div>
          <?php endif; ?>
        </dl>
      </div>

      <!-- Performance section - add when you have speed data -->
      <!-- <div class="yacht-spec-group">
        <h3>Performance</h3>
        <dl>
          Only show if values exist (not 0/empty)
        </dl>
      </div> -->

      <!-- Accommodations section - add when you have room data -->
      <!-- <div class="yacht-spec-group">
        <h3>Accommodations</h3>
        <dl>
          Only show rows with non-zero values
        </dl>
      </div> -->

      <!-- Engines section - add when you have engine data -->
      <!-- <div class="yacht-spec-group">
        <h3>Engines</h3>
        <dl>
          Loop over engines array
        </dl>
      </div> -->

    </div>
  </section>

  <!-- LOCATION / STATUS STRIP -->
  <section class="yacht-location-status">
    <?php if ( $location_display ) : ?>
    <p>
      <strong>Location:</strong>
      <?php echo yacht_output( $location_display ); ?>
    </p>
    <?php endif; ?>
    
    <?php if ( $status_text || $agreement_type ) : ?>
    <p>
      <strong>Status:</strong>
      <?php 
      $status_parts = array();
      if ( $status_text ) $status_parts[] = $status_text;
      if ( $agreement_type ) $status_parts[] = $agreement_type;
      echo yacht_output( implode( ' (', $status_parts ) . ( count( $status_parts ) > 1 ? ')' : '' ) );
      ?>
    </p>
    <?php endif; ?>
    
    <?php if ( $days_on_market ) : ?>
    <p>
      <strong>Days on Market:</strong>
      <?php echo yacht_output( $days_on_market ); ?>
    </p>
    <?php endif; ?>
  </section>

  <!-- BROKER & ENQUIRY -->
  <section class="yacht-contact">
    <?php if ( $broker_first_name || $broker_last_name || $company_name ) : ?>
    <div class="yacht-broker-card">
      <h2>Broker</h2>

      <?php if ( $broker_photo_url ) : ?>
      <img
        src="<?php echo esc_url( $broker_photo_url ); ?>"
        alt="Broker photo"
        class="broker-photo"
      >
      <?php endif; ?>

      <?php if ( $broker_first_name || $broker_last_name ) : ?>
      <p class="broker-name"><?php echo yacht_output( trim( $broker_first_name . ' ' . $broker_last_name ) ); ?></p>
      <?php endif; ?>
      
      <?php if ( $company_name ) : ?>
      <p class="broker-company"><?php echo yacht_output( $company_name ); ?></p>
      <?php endif; ?>
      
      <?php if ( $broker_phone ) : ?>
      <p class="broker-phone"><?php echo yacht_output( $broker_phone ); ?></p>
      <?php endif; ?>
      
      <?php if ( $broker_email ) : ?>
      <p class="broker-email">
        <a href="mailto:<?php echo esc_attr( $broker_email ); ?>"><?php echo esc_html( $broker_email ); ?></a>
      </p>
      <?php endif; ?>

      <?php if ( $company_logo_url ) : ?>
      <img
        src="<?php echo esc_url( $company_logo_url ); ?>"
        alt="<?php echo esc_attr( $company_name ?: 'Company logo' ); ?>"
        class="company-logo"
      >
      <?php endif; ?>

      <?php if ( $company_address ) : ?>
      <p class="company-address">
        <?php echo yacht_output( $company_address ); ?>
      </p>
      <?php endif; ?>
      
      <?php if ( $company_website ) : ?>
      <p class="company-website">
        <a href="<?php echo esc_url( $company_website ); ?>" target="_blank"><?php echo esc_html( str_replace( array( 'http://', 'https://' ), '', $company_website ) ); ?></a>
      </p>
      <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- Enquiry form (our own form, not from API) -->
    <div class="yacht-enquiry-form" id="yacht-enquiry">
      <h2>Enquire About This Vessel</h2>

      <form action="" method="post">
        <div class="form-row">
          <label for="name">Name *</label>
          <input type="text" id="name" name="name" required>
        </div>

        <div class="form-row">
          <label for="email">Email *</label>
          <input type="email" id="email" name="email" required>
        </div>

        <div class="form-row">
          <label for="phone">Phone</label>
          <input type="tel" id="phone" name="phone">
        </div>

        <div class="form-row">
          <label for="message">Message</label>
          <textarea id="message" name="message" rows="4"></textarea>
        </div>

        <!-- Hidden fields for routing -->
        <input type="hidden" name="vessel_id" value="<?php echo esc_attr( $post_id ); ?>">
        <input type="hidden" name="vessel_name" value="<?php echo esc_attr( $yacht_title ); ?>">
        <input type="hidden" name="builder" value="<?php echo esc_attr( $builder ); ?>">
        <input type="hidden" name="year_built" value="<?php echo esc_attr( $year_built ); ?>">

        <button type="submit" class="btn-primary">Send Enquiry</button>
      </form>
    </div>
  </section>

  <!-- ABOUT THE BUILDER -->
  <?php 
  $builder_description = yacht_meta( 'yacht_builder_description', '' );
  if ( $builder_description || $company_name ) : 
  ?>
  <section class="yacht-builder">
    <h2>About <?php echo yacht_output( $builder ?: $company_name ); ?></h2>
    <?php if ( $builder_description ) : ?>
    <p>
      <?php echo yacht_output_html( $builder_description ); ?>
    </p>
    <?php endif; ?>
  </section>
  <?php endif; ?>

</article>

<?php
// Get WordPress footer
get_footer();
?>

