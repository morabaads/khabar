<?php
/**
 * End-to-end integration test. Run inside a WordPress + WooCommerce install:
 *
 *   wp eval-file wp-content/plugins/khabar/tests/integration.php
 *
 * Network sends are intercepted (SMS / email / WhatsApp) so nothing leaves the machine.
 *
 * @package Khabar
 */

// phpcs:ignoreFile

$GLOBALS['khabar_sms']   = array();
$GLOBALS['khabar_mail']  = array();
$GLOBALS['khabar_fail']  = 0;
$GLOBALS['khabar_pass']  = 0;

add_filter( 'khabar_pre_send_sms', function ( $pre, $to, $text, $event, $vars ) {
	$GLOBALS['khabar_sms'][] = compact( 'to', 'text', 'event', 'vars' );
	return true;
}, 10, 5 );
add_filter( 'pre_wp_mail', function ( $pre, $atts ) {
	$GLOBALS['khabar_mail'][] = $atts;
	return true;
}, 10, 2 );
add_filter( 'pre_http_request', function ( $pre, $args, $url ) {
	return array( 'response' => array( 'code' => 201, 'message' => 'Created' ), 'body' => '{}', 'headers' => array(), 'cookies' => array() );
}, 10, 3 );

function t( $cond, $label ) {
	if ( $cond ) {
		++$GLOBALS['khabar_pass'];
		echo "  ✔ {$label}\n";
	} else {
		++$GLOBALS['khabar_fail'];
		echo "  ✘ FAIL: {$label}\n";
	}
}

function settings( $over ) {
	$all = Khabar_Settings::all();
	update_option( Khabar_Settings::OPTION, array_merge( $all, $over ) );
	Khabar_Settings::flush();
}

function rest( $method, $path, $params = array() ) {
	$req = new WP_REST_Request( $method, '/khabar/v1/' . $path );
	foreach ( $params as $k => $v ) {
		$req->set_param( $k, $v );
	}
	$res = rest_do_request( $req );
	return array( $res->get_status(), $res->get_data() );
}

function reset_capture() {
	$GLOBALS['khabar_sms']  = array();
	$GLOBALS['khabar_mail'] = array();
}

function sub_status( $id ) {
	return Khabar_Subscriptions::get( $id )->status;
}

function attr_term( $tax, $name ) {
	$t = get_term_by( 'name', $name, $tax );
	if ( ! $t ) {
		$t = (object) wp_insert_term( $name, $tax );
		$t = get_term( $t->term_id, $tax );
	}
	return $t;
}

global $wpdb;
wp_set_current_user( 0 );
echo "== Setup ==\n";
foreach ( array( 'subscriptions', 'log', 'notifications', 'push' ) as $tb ) {
	t( $wpdb->get_var( "SELECT COUNT(*) FROM " . Khabar_Install::table( $tb ) ) !== null, "table {$tb} exists" );
}
$wpdb->query( 'DELETE FROM ' . Khabar_Install::table( 'subscriptions' ) );
$wpdb->query( 'DELETE FROM ' . Khabar_Install::table( 'log' ) );
settings( array( 'channels_enabled' => array( 'sms', 'email', 'onsite', 'push' ), 'verify_contact' => 'none', 'rate_limit' => 0, 'waves_enabled' => 0, 'exclusive_enabled' => 0, 'low_stock_threshold' => 5 ) );

// Attributes.
foreach ( array( 'color' => 'رنگ', 'size' => 'سایز' ) as $slug => $label ) {
	if ( ! wc_attribute_taxonomy_id_by_name( $slug ) ) {
		wc_create_attribute( array( 'name' => $label, 'slug' => $slug ) );
	}
	register_taxonomy( 'pa_' . $slug, 'product' );
}
$blue  = attr_term( 'pa_color', 'آبی' );
$black = attr_term( 'pa_color', 'مشکی' );
$s41   = attr_term( 'pa_size', '41' );
$s42   = attr_term( 'pa_size', '42' );

// Variable product: Nike shoe.
$p = new WC_Product_Variable();
$p->set_name( 'کفش Nike' );
$p->set_status( 'publish' );
$attrs = array();
foreach ( array( 'pa_color' => array( $blue, $black ), 'pa_size' => array( $s41, $s42 ) ) as $tax => $terms ) {
	$a = new WC_Product_Attribute();
	$a->set_id( wc_attribute_taxonomy_id_by_name( $tax ) );
	$a->set_name( $tax );
	$a->set_options( wp_list_pluck( $terms, 'term_id' ) );
	$a->set_variation( true );
	$a->set_visible( true );
	$attrs[] = $a;
}
$p->set_attributes( $attrs );
$pid = $p->save();
foreach ( array( array( $blue, $s41 ), array( $blue, $s42 ), array( $black, $s42 ) ) as $combo ) {
	wp_set_object_terms( $pid, array( $combo[0]->slug ), 'pa_color', true );
	wp_set_object_terms( $pid, array( $combo[1]->slug ), 'pa_size', true );
}
$vars = array();
foreach ( array( 'blue41' => array( $blue, $s41, 3000000 ), 'blue42' => array( $blue, $s42, 3000000 ), 'black42' => array( $black, $s42, 3500000 ) ) as $k => $c ) {
	$v = new WC_Product_Variation();
	$v->set_parent_id( $pid );
	$v->set_attributes( array( 'pa_color' => $c[0]->slug, 'pa_size' => $c[1]->slug ) );
	$v->set_regular_price( $c[2] );
	$v->set_manage_stock( true );
	$v->set_stock_quantity( 0 );
	$v->set_stock_status( 'outofstock' );
	$v->set_status( 'publish' );
	$vars[ $k ] = $v->save();
}
WC_Product_Variable::sync( $pid );
t( (bool) $pid && count( $vars ) === 3, 'variable product with 3 variations created' );

// Simple product: Sony headphone in stock 9,000,000.
$h = new WC_Product_Simple();
$h->set_name( 'هدفون Sony' );
$h->set_status( 'publish' );
$h->set_regular_price( 9000000 );
$h->set_manage_stock( true );
$h->set_stock_quantity( 10 );
$hid = $h->save();

// Simple out of stock product.
$o = new WC_Product_Simple();
$o->set_name( 'ساعت' );
$o->set_status( 'publish' );
$o->set_regular_price( 1000000 );
$o->set_manage_stock( true );
$o->set_stock_quantity( 0 );
$oid = $o->save();

echo "\n== Flow 1: subscribe (guest, exact variation by attributes) ==\n";
list( $code, $data ) = rest( 'POST', 'subscribe', array( 'product_id' => $pid, 'attributes' => array( 'attribute_pa_color' => $blue->slug, 'attribute_pa_size' => $s42->slug ), 'in_stock' => 1, 'phone' => '۰۹۱۲۱۲۳۴۵۶۷', 'name' => 'علی', 'channels' => array( 'sms', 'onsite' ) ) );
t( 200 === $code && ! empty( $data['ids'] ), 'subscribe ok (' . $code . ' ' . ( $data['message'] ?? '' ) . ')' );
$sub_blue42 = $data['ids'][0];
$s = Khabar_Subscriptions::get( $sub_blue42 );
t( $s->variation_id === $vars['blue42'] && ! $s->attributes, 'full attribute set resolved to the exact variation' );
t( '09121234567' === $s->phone, 'Persian digits normalized to 09121234567' );
t( 'stock' === $s->type, 'type = stock' );

list( $code, $data ) = rest( 'POST', 'subscribe', array( 'product_id' => $pid, 'attributes' => array( 'attribute_pa_size' => $s42->slug ), 'in_stock' => 1, 'email' => 'size42@example.com' ) );
$sub_any42 = $data['ids'][0];
t( 200 === $code && array( 'attribute_pa_size' => $s42->slug ) === Khabar_Subscriptions::get( $sub_any42 )->attributes, 'partial expectation (size 42, any color) stored' );

list( $code, $data ) = rest( 'POST', 'subscribe', array( 'product_id' => $pid, 'attributes' => array( 'attribute_pa_color' => $blue->slug, 'attribute_pa_size' => $s42->slug ), 'in_stock' => 1, 'phone' => '09121234567' ) );
t( $data['ids'][0] === $sub_blue42, 'duplicate request merged into the existing one (Feature 10)' );

list( $code, $data ) = rest( 'POST', 'subscribe', array( 'product_id' => $hid, 'in_stock' => 1, 'phone' => '09120000000' ) );
t( 400 === $code, 'stock alert rejected for an in-stock simple product: ' . ( $data['message'] ?? '' ) );

list( $code, $data ) = rest( 'POST', 'subscribe', array( 'product_id' => $oid, 'in_stock' => 1, 'phone' => '12345' ) );
t( 400 === $code, 'invalid phone rejected' );

list( $code, $data ) = rest( 'POST', 'subscribe', array( 'product_id' => $oid, 'in_stock' => 1 ) );
t( 400 === $code, 'missing contact rejected' );

list( $code, $data ) = rest( 'POST', 'subscribe', array( 'product_id' => $oid, 'in_stock' => 1, 'phone' => '09120000001', 'website' => 'spam' ) );
t( 200 === $code && empty( $data['ids'] ), 'honeypot silently drops bots' );

echo "\n== Flow 2 / Feature 1 & 14: exact variation restock ==\n";
reset_capture();
$v = wc_get_product( $vars['blue41'] );
wc_update_product_stock( $v, 5, 'set' );
Khabar_Watcher::flush();
Khabar_Dispatcher::check_product( $pid );
t( 'active' === sub_status( $sub_blue42 ), 'blue/41 restock does NOT notify the blue/42 subscriber' );
t( 'active' === sub_status( $sub_any42 ), 'blue/41 restock does NOT notify size-42 subscriber' );

$v = wc_get_product( $vars['blue42'] );
wc_update_product_stock( $v, 2, 'set' );
$pending = Khabar_Watcher::pending();
t( isset( $pending[ $pid ] ), 'stock change of a variation queues its parent product' );
Khabar_Watcher::flush();
t( function_exists( 'as_has_scheduled_action' ) && as_has_scheduled_action( 'khabar_check_product', array( $pid ), 'khabar' ), 'check scheduled in Action Scheduler' );
$sent = Khabar_Dispatcher::check_product( $pid );
t( 2 === $sent, "2 subscribers notified (got {$sent})" );
t( 'notified' === sub_status( $sub_blue42 ) && 'notified' === sub_status( $sub_any42 ), 'both subscriptions marked notified' );
$sms = $GLOBALS['khabar_sms'][0] ?? null;
t( $sms && 'low_stock' === $sms['event'], 'stock 2 ≤ threshold → low_stock template (Feature 11)' );
t( $sms && false !== strpos( $sms['text'], 'فقط 2 عدد' ) && false !== strpos( $sms['text'], 'آبی' ) && false !== strpos( $sms['text'], 'کفش Nike' ), 'SMS text: ' . str_replace( "\n", ' | ', $sms['text'] ?? '' ) );
t( false !== strpos( $sms['text'] ?? '', 'kgo=' ), 'SMS contains tracking link' );
t( 1 === count( $GLOBALS['khabar_mail'] ), 'email sent to size-42 subscriber' );
$owner = Khabar_Subscriptions::get( $sub_blue42 )->owner_key;
t( Khabar_Channel_Onsite::unread( $owner ) >= 1, 'on-site notification stored for the guest owner key' );

reset_capture();
wc_update_product_stock( wc_get_product( $vars['blue42'] ), 4, 'set' );
Khabar_Dispatcher::check_product( $pid );
t( 0 === count( $GLOBALS['khabar_sms'] ), 'further stock changes send nothing again (Feature 10)' );

echo "\n== Flow 3 / Feature 4: price drop ==\n";
list( $code, $data ) = rest( 'POST', 'subscribe', array( 'product_id' => $hid, 'price_below' => '۸٬۰۰۰٬۰۰۰', 'email' => 'drop@example.com' ) );
$sub_drop = $data['ids'][0] ?? 0;
t( 200 === $code && 8000000.0 === Khabar_Subscriptions::get( $sub_drop )->price_below, 'target price parsed from Persian digits with separators' );
list( $code, $data ) = rest( 'POST', 'subscribe', array( 'product_id' => $hid, 'price_below' => 9500000, 'email' => 'x@example.com' ) );
t( 400 === $code, 'target price above current price rejected' );
reset_capture();
$h = wc_get_product( $hid );
$h->set_sale_price( 8500000 );
$h->save();
Khabar_Watcher::flush();
Khabar_Dispatcher::check_product( $hid );
t( 'active' === sub_status( $sub_drop ), '8.5M does not satisfy ≤ 8M' );
$h->set_sale_price( 7900000 );
$h->save();
Khabar_Dispatcher::check_product( $hid );
t( 'notified' === sub_status( $sub_drop ), '7.9M satisfies ≤ 8M → notified' );
$mail = $GLOBALS['khabar_mail'][0] ?? array();
t( isset( $mail['subject'] ) && false !== strpos( $mail['subject'], 'کاهش' ), 'price drop email subject: ' . ( $mail['subject'] ?? '' ) );

echo "\n== Feature 5: price rise ==\n";
list( $code, $data ) = rest( 'POST', 'subscribe', array( 'product_id' => $hid, 'price_above' => 10000000, 'phone' => '09125550000' ) );
$sub_rise = $data['ids'][0];
$h = wc_get_product( $hid );
$h->set_sale_price( '' );
$h->set_regular_price( 10500000 );
$h->save();
Khabar_Dispatcher::check_product( $hid );
t( 'notified' === sub_status( $sub_rise ), 'price above 10M → notified' );

echo "\n== Feature 6: recurring per-variation price change + cooldown ==\n";
list( $code, $data ) = rest( 'POST', 'subscribe', array( 'product_id' => $pid, 'variation_id' => $vars['black42'], 'price_change' => 1, 'phone' => '09126660000' ) );
$sub_change = $data['ids'][0];
t( 'price_change' === Khabar_Subscriptions::get( $sub_change )->type, 'type = price_change' );
settings( array( 'price_requires_stock' => 0, 'price_change_cooldown' => 0 ) );
reset_capture();
$bv = wc_get_product( $vars['blue42'] );
$bv->set_regular_price( 2800000 );
$bv->save();
Khabar_Dispatcher::check_product( $pid );
t( 0 === count( $GLOBALS['khabar_sms'] ), 'blue/42 price change does not alert black/42 follower' );
$kv = wc_get_product( $vars['black42'] );
$kv->set_regular_price( 3200000 );
$kv->save();
Khabar_Dispatcher::check_product( $pid );
$s = Khabar_Subscriptions::get( $sub_change );
t( 'active' === $s->status && 1 === (int) $s->notified_count && 3200000.0 === $s->base_price, 'black/42 change notified, stays active, baseline updated' );
t( false !== strpos( $GLOBALS['khabar_sms'][0]['text'] ?? '', '3,500,000' ), 'old price in message: ' . str_replace( "\n", ' | ', $GLOBALS['khabar_sms'][0]['text'] ?? '' ) );
settings( array( 'price_change_cooldown' => 24 ) );
$kv->set_regular_price( 3100000 );
$kv->save();
Khabar_Dispatcher::check_product( $pid );
t( 1 === (int) Khabar_Subscriptions::get( $sub_change )->notified_count, 'second change within cooldown suppressed' );
settings( array( 'price_requires_stock' => 1 ) );

echo "\n== Feature 15: combined rule ==\n";
list( $code, $data ) = rest( 'POST', 'subscribe', array( 'product_id' => $pid, 'attributes' => array( 'attribute_pa_color' => $black->slug, 'attribute_pa_size' => $s42->slug ), 'in_stock' => 1, 'price_below' => 3000000, 'min_qty' => 3, 'phone' => '09127770000' ) );
$sub_combo = $data['ids'][0];
t( 'combo' === Khabar_Subscriptions::get( $sub_combo )->type, 'type = combo' );
$kv = wc_get_product( $vars['black42'] );
wc_update_product_stock( $kv, 5, 'set' );
Khabar_Dispatcher::check_product( $pid );
t( 'active' === sub_status( $sub_combo ), 'in stock (5) but price 3.1M > 3M → waits' );
$kv = wc_get_product( $vars['black42'] );
$kv->set_sale_price( 2900000 );
wc_update_product_stock( $kv, 2, 'set' );
$kv->save();
Khabar_Dispatcher::check_product( $pid );
t( 'active' === sub_status( $sub_combo ), 'price ok but qty 2 < 3 → waits' );
$kv = wc_get_product( $vars['black42'] );
wc_update_product_stock( $kv, 4, 'set' );
Khabar_Dispatcher::check_product( $pid );
t( 'notified' === sub_status( $sub_combo ), 'all conditions met → notified' );

echo "\n== Separate mode creates one alert per condition ==\n";
list( $code, $data ) = rest( 'POST', 'subscribe', array( 'product_id' => $oid, 'in_stock' => 1, 'price_below' => 900000, 'mode' => 'separate', 'email' => 'sep@example.com' ) );
t( 2 === count( $data['ids'] ?? array() ), 'two subscriptions created' );

echo "\n== Waves (limited stock fairness) ==\n";
$wpdb->query( $wpdb->prepare( 'UPDATE ' . Khabar_Install::table( 'subscriptions' ) . " SET status = 'cancelled' WHERE product_id = %d", $oid ) );
settings( array( 'waves_enabled' => 1, 'waves_multiplier' => 2, 'waves_interval' => 60 ) );
$ids = array();
for ( $i = 0; $i < 5; $i++ ) {
	list( , $d ) = rest( 'POST', 'subscribe', array( 'product_id' => $oid, 'in_stock' => 1, 'phone' => '0912800000' . $i ) );
	$ids[] = $d['ids'][0];
}
wc_update_product_stock( wc_get_product( $oid ), 1, 'set' );
Khabar_Dispatcher::check_product( $oid );
$notified = array_filter( $ids, function ( $id ) { return 'notified' === sub_status( $id ); } );
t( 2 === count( $notified ), 'stock 1 × multiplier 2 → only 2 of 5 waves notified (got ' . count( $notified ) . ')' );
t( array_values( $notified ) === array_slice( $ids, 0, 2 ), 'first-come first-served order' );
settings( array( 'waves_enabled' => 0 ) );

echo "\n== Feature 12: exclusive early access ==\n";
settings( array( 'exclusive_enabled' => 1, 'exclusive_minutes' => 30 ) );
$e = new WC_Product_Simple();
$e->set_name( 'کالای محدود' );
$e->set_status( 'publish' );
$e->set_regular_price( 500000 );
$e->set_manage_stock( true );
$e->set_stock_quantity( 0 );
$eid = $e->save();
list( , $d ) = rest( 'POST', 'subscribe', array( 'product_id' => $eid, 'in_stock' => 1, 'phone' => '09129990000' ) );
$sub_ex = Khabar_Subscriptions::get( $d['ids'][0] );
reset_capture();
wc_update_product_stock( wc_get_product( $eid ), 3, 'set' );
Khabar_Dispatcher::check_product( $eid );
t( Khabar_Reservation::remaining( $eid ) > 1700, 'exclusive window opened (~30 min)' );
t( false !== strpos( $GLOBALS['khabar_sms'][0]['text'] ?? '', '30 دقیقه' ), 'message mentions 30-minute exclusive window' );
unset( $_COOKIE[ Khabar_Utils::ACCESS_COOKIE ] );
t( false === wc_get_product( $eid )->is_purchasable(), 'public cannot buy during exclusive window' );
$_COOKIE[ Khabar_Utils::ACCESS_COOKIE ] = $sub_ex->token;
t( true === wc_get_product( $eid )->is_purchasable(), 'notified subscriber (token cookie) can buy' );
unset( $_COOKIE[ Khabar_Utils::ACCESS_COOKIE ] );
settings( array( 'exclusive_enabled' => 0 ) );
t( true === wc_get_product( $eid )->is_purchasable(), 'disabled → purchasable again' );

echo "\n== Conversion tracking ==\n";
$order = wc_create_order();
$order->add_product( wc_get_product( $vars['blue42'] ), 1 );
$order->set_billing_phone( '09121234567' );
$order->set_billing_email( 'ali@example.com' );
$order->calculate_totals();
$order->save();
$order->update_status( 'processing' );
$s = Khabar_Subscriptions::get( $sub_blue42 );
t( 'purchased' === $s->status && (int) $s->order_id === $order->get_id(), 'order matched by phone → purchased' );
t( (float) $s->order_value > 0, 'order value recorded: ' . $s->order_value );

echo "\n== Flow 4: customer panel via token ==\n";
list( $code, $list ) = rest( 'GET', 'subscriptions', array( 'token' => Khabar_Subscriptions::get( $sub_any42 )->token ) );
t( 200 === $code && 1 <= count( $list ), 'list subscriptions by token' );
unset( $_COOKIE[ Khabar_Utils::GUEST_COOKIE ] );
list( $code, ) = rest( 'GET', 'subscriptions', array( 'token' => 'bogus' ) );
t( 401 === $code, 'invalid token refused' );
list( , $d ) = rest( 'POST', 'subscribe', array( 'product_id' => $hid, 'price_below' => 9000000, 'email' => 'edit@example.com' ) );
Khabar_Subscriptions::update( $d['ids'][0], array( 'owner_key' => 'g:someoneelse' ) );
unset( $_COOKIE[ Khabar_Utils::GUEST_COOKIE ] );
$sub_edit = Khabar_Subscriptions::get( $d['ids'][0] );
list( $code, $d ) = rest( 'POST', 'subscriptions/' . $sub_edit->id, array( 'token' => $sub_edit->token, 'price_below' => '8,500,000', 'phone' => '09121112233' ) );
$e2 = Khabar_Subscriptions::get( $sub_edit->id );
t( 200 === $code && 8500000.0 === $e2->price_below && '09121112233' === $e2->phone, 'edit target price and contact' );
list( $code, ) = rest( 'DELETE', 'subscriptions/' . $sub_edit->id, array( 'token' => Khabar_Subscriptions::get( $sub_blue42 )->token ) );
t( 403 === $code, 'cannot delete someone else\'s request' );
list( $code, ) = rest( 'DELETE', 'subscriptions/' . $sub_edit->id, array( 'token' => $sub_edit->token ) );
t( 200 === $code && 'cancelled' === sub_status( $sub_edit->id ), 'delete (cancel) own request' );

echo "\n== OTP verification ==\n";
settings( array( 'verify_contact' => 'guest' ) );
reset_capture();
list( $code, $d ) = rest( 'POST', 'subscribe', array( 'product_id' => $oid, 'price_change' => 1, 'phone' => '09123334444' ) );
t( ! empty( $d['need_code'] ), 'guest must verify: ' . ( $d['message'] ?? '' ) );
$otp = $GLOBALS['khabar_sms'][0]['vars']['code'] ?? '';
t( 5 === strlen( $otp ), 'OTP sent by SMS' );
global $wpdb;
$pending_id = (int) $wpdb->get_var( "SELECT id FROM " . Khabar_Install::table( 'subscriptions' ) . " WHERE phone = '09123334444'" );
t( 'pending' === sub_status( $pending_id ), 'subscription pending until verified' );
list( $code, ) = rest( 'POST', 'verify', array( 'contact' => '09123334444', 'code' => '00000' ) );
t( 400 === $code, 'wrong code rejected' );
list( $code, ) = rest( 'POST', 'verify', array( 'contact' => '09123334444', 'code' => $otp ) );
t( 200 === $code && 'active' === sub_status( $pending_id ), 'correct code activates' );
settings( array( 'verify_contact' => 'none' ) );

echo "\n== Push (VAPID) ==\n";
$keys = Khabar_Push::keys();
t( $keys && 87 === strlen( $keys['public'] ), 'VAPID key generated (65-byte uncompressed point)' );
$jwt   = Khabar_Push::jwt( 'https://fcm.googleapis.com/fcm/send/abc', $keys );
$parts = explode( '.', $jwt );
$raw   = base64_decode( strtr( $parts[2], '-_', '+/' ) . str_repeat( '=', ( 4 - strlen( $parts[2] ) % 4 ) % 4 ) );
$int   = function ( $b ) { $b = ltrim( $b, "\0" ); if ( ord( $b[0] ) > 0x7f ) { $b = "\0" . $b; } return "\x02" . chr( strlen( $b ) ) . $b; };
$der   = $int( substr( $raw, 0, 32 ) ) . $int( substr( $raw, 32 ) );
$der   = "\x30" . chr( strlen( $der ) ) . $der;
$pub   = openssl_pkey_get_details( openssl_pkey_get_private( $keys['pem'] ) )['key'];
t( 1 === openssl_verify( $parts[0] . '.' . $parts[1], $der, $pub, OPENSSL_ALGO_SHA256 ), 'ES256 JWT signature verifies' );
t( Khabar_Push::save_subscription( 'g:testowner', array( 'endpoint' => 'https://fcm.googleapis.com/fcm/send/xyz', 'keys' => array( 'p256dh' => 'a', 'auth' => 'b' ) ) ), 'browser subscription saved' );
Khabar_Channel_Onsite::create( 'g:testowner', 0, 'عنوان', 'متن', 'https://example.com' );
t( true === Khabar_Push::notify_owner( 'g:testowner' ), 'push ping sent (HTTP mocked)' );
$latest = Khabar_Push::latest_for_endpoint( 'https://fcm.googleapis.com/fcm/send/xyz' );
t( $latest && 'عنوان' === $latest['title'], 'service worker payload endpoint returns latest notification' );

echo "\n== Rendering ==\n";
$GLOBALS['product'] = wc_get_product( $pid );
$html = Khabar_Frontend::render( wc_get_product( $pid ) );
t( false !== strpos( $html, 'khabar-modal' ) && false !== strpos( $html, 'attribute_pa_size' ), 'variable product widget renders with attribute selectors' );
$html = Khabar_Frontend::render( wc_get_product( $oid ) );
t( false !== strpos( $html, 'خبرم کن وقتی موجود شد' ), 'simple OOS widget renders stock button' );
wp_set_current_user( 1 );
$_SERVER['HTTP_HOST'] = 'localhost';
foreach ( array( 'page_dashboard', 'page_requests', 'page_reports', 'page_logs', 'page_settings' ) as $page ) {
	ob_start();
	if ( 'page_reports' === $page ) {
		$_GET['product_id'] = $pid;
	}
	Khabar_Admin::$page();
	$out = ob_get_clean();
	t( strlen( $out ) > 200, "admin {$page} renders (" . strlen( $out ) . ' bytes)' );
}
$demand = Khabar_Reports::attribute_demand( $pid );
t( ! empty( $demand ), 'attribute demand report: ' . wp_json_encode( $demand, JSON_UNESCAPED_UNICODE ) );
$k = Khabar_Reports::kpis();
t( $k['conversions'] >= 1 && $k['revenue'] > 0, 'KPIs: ' . wp_json_encode( $k ) );
t( 0 === $k['failed'], 'no failed sends logged' );
wp_set_current_user( 0 );

echo "\n== Result: {$GLOBALS['khabar_pass']} passed, {$GLOBALS['khabar_fail']} failed ==\n";
if ( $GLOBALS['khabar_fail'] ) {
	exit( 1 );
}
