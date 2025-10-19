<?php
// Shared site footer for all user pages
// Self-contained styles (scoped with .snb-footer) so it works on Tailwind or Bootstrap pages
?>
<style>
.snb-footer { background:#153f32; color:#e7f0ec; }
.snb-footer .container { max-width: 1200px; margin: 0 auto; padding: 2rem 1.5rem; }
.snb-footer .grid { display: grid; grid-template-columns: repeat(1, minmax(0,1fr)); gap: 2rem; }
@media (min-width: 768px) { .snb-footer .grid { grid-template-columns: repeat(4, minmax(0,1fr)); } }
.snb-footer h3, .snb-footer h4 { margin: 0 0 .75rem; color:#D4AF37; font-weight: 700; }
.snb-footer p { margin: 0 0 .75rem; opacity:.9; }
.snb-footer a { color: #e7f0ec; text-decoration: none; opacity:.9; }
.snb-footer a:hover { color:#D4AF37; opacity:1; }
.snb-footer .brand { display:flex; align-items:center; gap:.75rem; margin-bottom: .75rem; }
.snb-footer .brand-logo { width:48px; height:48px; border-radius:9999px; display:flex; align-items:center; justify-content:center; background:linear-gradient(135deg,#f2c94c,#ef9d27); color:#1B4332; font-weight: 800; }
.snb-footer .list { list-style:none; padding:0; margin:0; display:grid; gap:.5rem; }
.snb-footer .contact-item { display:flex; align-items:flex-start; gap:.5rem; }
.snb-footer .contact-item i { color:#D4AF37; margin-top:.2rem; min-width: 16px; }
.snb-footer .social { display:flex; gap:.75rem; }
.snb-footer .divider { height:1px; background: rgba(255,255,255,.2); margin-top: 1.25rem; }
.snb-footer .copy { text-align:center; padding: 1rem 0 0.5rem; font-size: .95rem; opacity:.9; }

/* Fade-up reveal animation (slower + smoother) */
.snb-footer .reveal { opacity: 0; transform: translateY(24px); transition: opacity .9s cubic-bezier(.22,1,.36,1), transform .9s cubic-bezier(.22,1,.36,1); will-change: opacity, transform; }
.snb-footer .reveal.show { opacity: 1; transform: translateY(0); }
.snb-footer .reveal.delay-1 { transition-delay: .12s; }
.snb-footer .reveal.delay-2 { transition-delay: .24s; }
.snb-footer .reveal.delay-3 { transition-delay: .36s; }
.snb-footer .reveal.delay-4 { transition-delay: .48s; }
.snb-footer .reveal.delay-5 { transition-delay: .60s; }

/* Respect reduced motion preferences */
@media (prefers-reduced-motion: reduce) {
	.snb-footer .reveal { opacity: 1; transform: none; transition: none; }
}

/* Subtle hover lift for cards/blocks */
.snb-footer .hover-lift { transition: transform .2s ease, color .2s ease; }
.snb-footer .hover-lift:hover { transform: translateY(-3px); }
.snb-footer .list a { position: relative; }
.snb-footer .list a::after { content:''; position:absolute; left:0; right:100%; bottom:-2px; height:2px; background:#D4AF37; transition:right .2s ease; }
.snb-footer .list a:hover::after { right:0; }
</style>

<!-- Optional icons (safe to include multiple times) -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />

<footer class="snb-footer">
		<div class="container">
			<div class="grid">
			<!-- Brand -->
				<div class="reveal hover-lift">
				<div class="brand">
					<div class="brand-logo">S</div>
					<div>
						<h3>Sandok ni Binggay</h3>
						<p style="color:#f4d58d; font-size:.85rem; letter-spacing:.08em;">CATERING SERVICES</p>
					</div>
				</div>
				<p>Nothing Beats Home-Cooked Meals</p>
			</div>

			<!-- Quick Links -->
				<div class="reveal delay-2 hover-lift">
				<h4>Quick Links</h4>
				<ul class="list">
					<li><a href="index">Home</a></li>
					<li><a href="menu">Menu</a></li>
					<li><a href="cateringpackages">Catering</a></li>
					<li><a href="booking">Occasions</a></li>
				</ul>
			</div>

			<!-- Contact -->
				<div class="reveal delay-3 hover-lift">
				<h4>Contact</h4>
				<div class="list">
					<div class="contact-item"><i class="fas fa-phone"></i><span>0919-230-8344</span></div>
					<div class="contact-item"><i class="fas fa-envelope"></i><span>riatriumfo06@gmail.com</span></div>
					<div class="contact-item"><i class="fas fa-map-marker-alt"></i><span>Metro Manila, Philippines</span></div>
				</div>
			</div>

			<!-- Follow Us -->
				<div class="reveal delay-4 hover-lift">
				<h4>Follow Us</h4>
				<div class="social">
					<a href="https://www.facebook.com/profile.php?id=100064113068426" target="_blank" aria-label="Facebook"><i class="fab fa-facebook fa-lg"></i></a>
					<a href="#" aria-label="Instagram"><i class="fab fa-instagram fa-lg"></i></a>
					<a href="#" aria-label="Twitter"><i class="fab fa-twitter fa-lg"></i></a>
				</div>
			</div>
		</div>

			<div class="divider reveal delay-5"></div>
			<div class="copy reveal delay-5">&copy; <?php echo date('Y'); ?> Sandok ni Binggay. All rights reserved.</div>
	</div>
  
</footer>

	<script>
	// Reveal footer columns on first intersection
	(function(){
		const root = document.currentScript && document.currentScript.parentElement ? document.currentScript.parentElement : document;
		const footer = document.querySelector('.snb-footer');
		if (!footer) return;
		const els = footer.querySelectorAll('.reveal');
		if (!('IntersectionObserver' in window)) {
			// Fallback: show immediately
			els.forEach(el => el.classList.add('show'));
			return;
		}
			const io = new IntersectionObserver((entries) => {
			entries.forEach(entry => {
				if (entry.isIntersecting) {
					entry.target.classList.add('show');
					io.unobserve(entry.target);
				}
			});
			}, { root: null, threshold: 0.08, rootMargin: '0px 0px 15% 0px' });
		els.forEach(el => io.observe(el));
	})();

	// Global per-item reveal engine (smooth + stagger)
	(function(){
		const SEL_ITEM = '.fade-reveal';
		const SEL_GROUP = '[data-reveal-group]';
		const SHOW_CLASS = 'show';
		const SEEN_ATTR = 'data-revealed';

		function applyReveal(el, delayMs){
			if (!el || el.getAttribute(SEEN_ATTR) === '1') return;
			el.style.transitionDelay = (delayMs/1000).toFixed(2) + 's';
			el.classList.add(SHOW_CLASS);
			el.setAttribute(SEEN_ATTR, '1');
		}

		function setupObserver(){
			if (!('IntersectionObserver' in window)) {
				document.querySelectorAll(SEL_ITEM).forEach(el => el.classList.add(SHOW_CLASS));
				return { observe: ()=>{}, disconnect: ()=>{} };
			}
			const io = new IntersectionObserver((entries)=>{
				entries.forEach(entry => {
					if (!entry.isIntersecting) return;
					const target = entry.target;
					const group = target.closest(SEL_GROUP);
					let baseDelay = 0;
					if (group) {
						const items = Array.from(group.querySelectorAll(SEL_ITEM));
						const idx = items.indexOf(target);
						baseDelay = Math.max(0, idx) * 80; // 80ms stagger per item
					}
					applyReveal(target, baseDelay);
					io.unobserve(target);
				});
			}, { threshold: 0.06, rootMargin: '0px 0px 18% 0px' });

			document.querySelectorAll(SEL_ITEM).forEach(el => io.observe(el));
			return io;
		}

		// Initial styles for fade-reveal
		const style = document.createElement('style');
		style.textContent = `
		.fade-reveal{opacity:0;transform:translateY(26px);transition:opacity 0.95s cubic-bezier(.22,1,.36,1),transform 0.95s cubic-bezier(.22,1,.36,1);will-change:opacity,transform}
		.fade-reveal.show{opacity:1;transform:translateY(0)}
		@media (prefers-reduced-motion: reduce){.fade-reveal{opacity:1;transform:none;transition:none}}
		`;
		document.head.appendChild(style);

		// Expose a refresh function for dynamic content
		let observer = setupObserver();
		window.SNBRevealRefresh = function(){
			try { if (observer && observer.disconnect) observer.disconnect(); } catch(_){}
			observer = setupObserver();
		};
	})();
	</script>
