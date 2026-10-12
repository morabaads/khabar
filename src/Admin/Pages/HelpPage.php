<?php
/**
 * Admin section: A short guide to each part of the plugin with links to the matching settings.
 *
 * @package YadamBiar
 */

namespace YadamBiar\Admin\Pages;

defined( 'ABSPATH' ) || exit;

use YadamBiar\Admin\AdminUrl;
use YadamBiar\Support\Icon;
/**
 * A short guide to each part of the plugin with links to the matching settings.
 */
final class HelpPage extends Page {

	/**
	 * Slug.
	 *
	 * @return string
	 */
	public function slug() {
		return 'help';
	}

	/**
	 * Label.
	 *
	 * @return string
	 */
	public function title() {
		return __( 'راهنما', 'yadambiar' );
	}

	/**
	 * Icon.
	 *
	 * @return string
	 */
	public function icon() {
		return 'help';
	}

	/**
	 * Render.
	 */
	public function render() {
		$set = function ( $tab ) {
			return AdminUrl::settings( $tab );
		};
		$steps = array(
			array( __( 'کانال ارسال را وصل کنید', 'yadambiar' ), __( 'تنظیمات ← کانال‌های ارسال: سامانه پیامک را تنظیم و «کانال‌های فعال» را تیک بزنید.', 'yadambiar' ) ),
			array( __( 'ارسال آزمایشی بگیرید', 'yadambiar' ), __( 'پایین همان صفحه، یک پیام تست به شماره خودتان بفرستید.', 'yadambiar' ) ),
			array( __( 'دکمه را در صفحه محصول ببینید', 'yadambiar' ), __( 'روی کالای ناموجود، دکمه «یادم بیار» خودکار نمایش داده می‌شود.', 'yadambiar' ) ),
			array( __( 'بقیه‌اش خودکار است', 'yadambiar' ), __( 'وقتی موجودی یا قیمت تغییر کند، به منتظران خبر داده می‌شود.', 'yadambiar' ) ),
		);
		$topics = array(
			array(
				'icon'  => 'bell',
				'title' => __( 'یادم بیار چطور کار می‌کند؟', 'yadambiar' ),
				'items' => array(
					__( 'مشتری روی «یادم بیار» می‌زند، تنوع (سایز/رنگ) و شرط را انتخاب می‌کند و موبایل یا ایمیلش را می‌دهد.', 'yadambiar' ),
					__( 'شرط‌ها: موجود شدن، کمتر شدن قیمت از عدد دلخواه، بیشتر شدن قیمت، هر تغییر قیمت، یا حداقل موجودی.', 'yadambiar' ),
					__( 'با تغییر موجودی یا قیمت (دستی، ایمپورت یا هر روش دیگر)، درخواست‌ها بررسی و پیام ارسال می‌شود.', 'yadambiar' ),
					__( 'به هر درخواست فقط یک بار خبر داده می‌شود؛ «هر تغییر قیمت» تا وقتی لغو نشود ادامه دارد.', 'yadambiar' ),
				),
				'links' => array( array( __( 'تنظیمات عمومی', 'yadambiar' ), $set( 'general' ) ) ),
			),
			array(
				'icon'  => 'send',
				'title' => __( 'کانال‌های ارسال', 'yadambiar' ),
				'items' => array(
					__( 'پیامک: سامانه و کلید را وارد کنید. برای خط خدماتی، «نوع ارسال» را روی پترن بگذارید و کد پترن هر رویداد را اضافه کنید.', 'yadambiar' ),
					__( 'ایمیل و اعلان داخل سایت: بدون تنظیم کار می‌کنند.', 'yadambiar' ),
					__( 'پوش: سایت باید HTTPS باشد؛ فقط آیکون را انتخاب کنید.', 'yadambiar' ),
					__( 'بله: با «سفیر» پیام مستقیم به شماره موبایل مشتری می‌رود. بدون سفیر، مشتری باید ربات را Start کند.', 'yadambiar' ),
					__( 'تلگرام: توکن و نام ربات را بدهید و «ثبت وب‌هوک ربات‌ها» را بزنید؛ مشتری با دکمه «دریافت در تلگرام» وصل می‌شود.', 'yadambiar' ),
					__( 'اعلام در کانال: موجود شدن کالاهای پرتقاضا در کانال تلگرام/بله پست می‌شود (ربات باید ادمین کانال باشد).', 'yadambiar' ),
				),
				'links' => array( array( __( 'کانال‌های ارسال', 'yadambiar' ), $set( 'channels' ) ) ),
			),
			array(
				'icon'  => 'chat',
				'title' => __( 'قالب پیام‌ها', 'yadambiar' ),
				'items' => array(
					__( 'برای هر رویداد یک متن کوتاه (پیامک و پیام‌رسان)، یک عنوان و یک متن ایمیل دارید.', 'yadambiar' ),
					__( 'متغیرها مثل {product_name}، {price} و {link} موقع ارسال با مقدار واقعی جایگزین می‌شوند.', 'yadambiar' ),
					__( 'هر وقت خواستید با «بازگردانی پیش‌فرض» به متن اولیه برگردید.', 'yadambiar' ),
				),
				'links' => array( array( __( 'قالب پیام‌ها', 'yadambiar' ), $set( 'templates' ) ) ),
			),
			array(
				'icon'  => 'trend',
				'title' => __( 'رشد فروش', 'yadambiar' ),
				'items' => array(
					__( 'کد تخفیف خودکار: برای هر منتظر یک کد یکبار مصرف و محدود به همان کالا ساخته و در پیام گذاشته می‌شود.', 'yadambiar' ),
					__( 'محصول جایگزین: اگر کسی مدت زیادی منتظر مانده، محصولات مشابهِ موجود به او پیشنهاد می‌شود.', 'yadambiar' ),
					__( 'تاریخچه قیمت: نمودار تغییر قیمت در صفحه محصول.', 'yadambiar' ),
				),
				'links' => array( array( __( 'رشد فروش', 'yadambiar' ), $set( 'growth' ) ) ),
			),
			array(
				'icon'  => 'grid',
				'title' => __( 'داشبورد، درخواست‌ها و گزارش‌ها', 'yadambiar' ),
				'items' => array(
					__( 'داشبورد: تعداد منتظران، پیام‌های ارسالی، کلیک، خرید و درآمد حاصل از اعلان‌ها.', 'yadambiar' ),
					__( 'درخواست‌ها: همه درخواست‌ها با جستجو، فیلتر، لغو/حذف گروهی و خروجی اکسل.', 'yadambiar' ),
					__( 'گزارش‌ها: پرتقاضاترین کالاها و سایز/رنگ‌ها.', 'yadambiar' ),
					__( 'لاگ ارسال: نتیجه هر پیام؛ پیام ناموفق را می‌توانید دوباره بفرستید.', 'yadambiar' ),
				),
				'links' => array(
					array( __( 'داشبورد', 'yadambiar' ), AdminUrl::view() ),
					array( __( 'لاگ ارسال', 'yadambiar' ), AdminUrl::view( 'logs' ) ),
				),
			),
			array(
				'icon'  => 'box',
				'title' => __( 'پیش‌بینی تقاضا', 'yadambiar' ),
				'items' => array(
					__( 'برای هر کالای ناموجود می‌گوید چند عدد سفارش بدهید، بر اساس تعداد منتظران و فروش قبلی.', 'yadambiar' ),
					__( 'زمان تحویل تامین‌کننده و تعداد روزهای پوشش را تغییر دهید تا عدد پیشنهادی به‌روز شود.', 'yadambiar' ),
					__( 'خروجی CSV آن را مستقیم برای تامین‌کننده بفرستید.', 'yadambiar' ),
				),
				'links' => array( array( __( 'پیش‌بینی تقاضا', 'yadambiar' ), AdminUrl::view( 'forecast' ) ) ),
			),
			array(
				'icon'  => 'gear',
				'title' => __( 'پیشرفته (اختیاری)', 'yadambiar' ),
				'items' => array(
					__( 'ارسال موجی: وقتی موجودی کم است، اول به همان تعداد نفر اول خبر داده می‌شود، نه به همه.', 'yadambiar' ),
					__( 'رزرو زودتر از دیگران: منتظران چند دقیقه فرصت خرید اختصاصی دارند.', 'yadambiar' ),
					__( 'تایید با کد: شماره/ایمیل مهمان با کد یکبار مصرف تایید می‌شود تا شماره دیگران ثبت نشود.', 'yadambiar' ),
				),
				'links' => array( array( __( 'پیشرفته', 'yadambiar' ), $set( 'advanced' ) ) ),
			),
			array(
				'icon'  => 'cube',
				'title' => __( 'نمایش در سایت', 'yadambiar' ),
				'items' => array(
					__( 'جای دکمه در صفحه محصول را از «تنظیمات عمومی ← محل نمایش» عوض کنید. رنگ‌ها در «ظاهر و رنگ‌ها» است.', 'yadambiar' ),
					__( 'شورت‌کدها: [yadambiar] دکمه، [yadambiar_my_alerts] پنل «یادآوری‌های من»، [yadambiar_bell] زنگوله اعلان‌ها، [yadambiar_price_history] نمودار قیمت.', 'yadambiar' ),
					__( 'در المنتور، ویجت‌ها در دسته «یادم بیار» هستند. اگر صفحه محصول را با المنتور ساخته‌اید، محل نمایش را «فقط با شورت‌کد» بگذارید.', 'yadambiar' ),
					__( 'مشتری درخواست‌هایش را در «حساب کاربری ← یادآوری‌های من» می‌بیند و ویرایش می‌کند.', 'yadambiar' ),
				),
				'links' => array( array( __( 'ظاهر و رنگ‌ها', 'yadambiar' ), $set( 'appearance' ) ) ),
			),
			array(
				'icon'  => 'help',
				'title' => __( 'پیام نرسید؟', 'yadambiar' ),
				'items' => array(
					__( 'لاگ ارسال را ببینید؛ علت خطا کنار هر پیام ناموفق نوشته شده است.', 'yadambiar' ),
					__( 'ارسال آزمایشی بگیرید تا مطمئن شوید سامانه پیامک یا ربات درست تنظیم شده است.', 'yadambiar' ),
					__( 'برای تلگرام و بله، دکمه «بررسی ربات‌ها» را بزنید.', 'yadambiar' ),
					__( 'در داشبورد کنار هر محصول دکمه «عیب‌یابی» را بزنید تا علت ارسال نشدن هر درخواست را ببینید.', 'yadambiar' ),
				),
				'links' => array( array( __( 'لاگ ارسال', 'yadambiar' ), AdminUrl::view( 'logs' ) ) ),
			),
		);
		?>
		<div class="yadambiar-view yadambiar-help">
			<section class="yadambiar-set-card">
				<header><h2><?php esc_html_e( 'شروع سریع', 'yadambiar' ); ?></h2></header>
				<ol class="yadambiar-steps">
					<?php foreach ( $steps as $i => $step ) : ?>
						<li><span class="yadambiar-step-n"><?php echo esc_html( number_format_i18n( $i + 1 ) ); ?></span><div><b><?php echo esc_html( $step[0] ); ?></b><small><?php echo esc_html( $step[1] ); ?></small></div></li>
					<?php endforeach; ?>
				</ol>
			</section>
			<section class="yadambiar-set-card">
				<header><h2><?php esc_html_e( 'بخش‌ها', 'yadambiar' ); ?></h2></header>
				<?php foreach ( $topics as $i => $topic ) : ?>
					<details class="yadambiar-acc" id="yadambiar-acc-help-<?php echo esc_attr( $i ); ?>">
						<summary><span class="yadambiar-acc-icon"><?php echo Icon::svg( $topic['icon'] ); // phpcs:ignore WordPress.Security.EscapeOutput ?></span><span class="yadambiar-acc-title"><b><?php echo esc_html( $topic['title'] ); ?></b></span><span class="yadambiar-acc-chev" aria-hidden="true"></span></summary>
						<div class="yadambiar-acc-body">
							<ul class="yadambiar-help-list">
								<?php foreach ( $topic['items'] as $item ) : ?>
									<li><?php echo esc_html( $item ); ?></li>
								<?php endforeach; ?>
							</ul>
							<p class="yadambiar-help-links">
								<?php foreach ( $topic['links'] as $link ) : ?>
									<a class="button button-small" href="<?php echo esc_url( $link[1] ); ?>"><?php echo esc_html( $link[0] ); ?> ←</a>
								<?php endforeach; ?>
							</p>
						</div>
					</details>
				<?php endforeach; ?>
			</section>
		</div>
		<?php
	}
}
