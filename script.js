const menuToggle = document.querySelector(".menu-toggle");
const navigation = document.querySelector(".primary-navigation");
const siteHeader = document.querySelector(".site-header");
const navigationLinks = navigation?.querySelectorAll('a[href^="#"]') ?? [];

function setMenuOpen(isOpen) {
  if (!menuToggle || !navigation) return;
  menuToggle.setAttribute("aria-expanded", String(isOpen));
  menuToggle.setAttribute("aria-label", isOpen ? "Tutup menu" : "Buka menu");
  navigation.classList.toggle("is-open", isOpen);
  document.body.classList.toggle("menu-open", isOpen);
  if (isOpen && siteHeader) navigation.style.top = `${siteHeader.getBoundingClientRect().bottom}px`;
  if (!isOpen) navigation.style.removeProperty("top");
}

menuToggle?.addEventListener("click", () => {
  setMenuOpen(menuToggle.getAttribute("aria-expanded") !== "true");
});
navigationLinks.forEach((link) => link.addEventListener("click", () => setMenuOpen(false)));
document.addEventListener("keydown", (event) => {
  if (event.key === "Escape") setMenuOpen(false);
});
window.addEventListener("resize", () => {
  if (window.innerWidth > 820) setMenuOpen(false);
  else if (menuToggle?.getAttribute("aria-expanded") === "true") setMenuOpen(true);
});

const year = document.querySelector("#current-year");
if (year) year.textContent = new Date().getFullYear();

function initHeroSlideshow() {
  const slideshow = document.querySelector("[data-hero-slideshow]");
  const slides = Array.from(slideshow?.querySelectorAll("[data-hero-slide]") ?? []);
  const controls = slideshow?.querySelector("[data-hero-controls]");
  const previousButton = controls?.querySelector("[data-hero-prev]");
  const nextButton = controls?.querySelector("[data-hero-next]");
  const toggleButton = controls?.querySelector("[data-hero-toggle]");
  const pauseIcon = toggleButton?.querySelector("[data-autoplay-pause-icon]");
  const playIcon = toggleButton?.querySelector("[data-autoplay-play-icon]");
  const currentNumber = controls?.querySelector("[data-hero-current]");
  const totalNumber = controls?.querySelector("[data-hero-total]");
  const status = controls?.querySelector("[data-hero-status]");
  if (!slideshow || slides.length < 2 || !controls || !previousButton || !nextButton || !toggleButton) return;

  let currentIndex = Math.max(0, slides.findIndex((slide) => slide.classList.contains("is-active")));
  const motionPreference = window.matchMedia ? window.matchMedia("(prefers-reduced-motion: reduce)") : null;
  let autoplayPaused = motionPreference?.matches ?? false;
  let pointerOver = false;
  let autoplayTimer = null;
  const autoplayDelay = 7000;
  const formatNumber = (number) => String(number).padStart(2, "0");

  function loadSlideImage(slide) {
    if (!slide.dataset.heroSrc) return;
    const currentSource = slide.getAttribute("src") || "";
    const placeholder = "data:image/gif;base64,R0lGODlhAQABAAD/ACwAAAAAAQABAAACADs=";
    if (currentSource === placeholder) {
      const source = safeImage(slide.dataset.heroSrc, "");
      if (source) slide.setAttribute("src", source);
    }
    delete slide.dataset.heroSrc;
  }

  function updateAutoplayControl() {
    toggleButton.setAttribute("aria-label", autoplayPaused ? "Lanjutkan slideshow otomatis" : "Jeda slideshow otomatis");
    if (pauseIcon) pauseIcon.hidden = autoplayPaused;
    if (playIcon) playIcon.hidden = !autoplayPaused;
  }

  function scheduleAutoplay() {
    if (autoplayTimer !== null) {
      window.clearTimeout(autoplayTimer);
      autoplayTimer = null;
    }
    if (autoplayPaused || pointerOver || document.hidden) return;
    autoplayTimer = window.setTimeout(() => {
      autoplayTimer = null;
      showSlide(currentIndex + 1, false);
    }, autoplayDelay);
  }

  function showSlide(index, announce = true, scheduleNext = true) {
    currentIndex = (index + slides.length) % slides.length;
    const activeSlide = slides[currentIndex];
    loadSlideImage(activeSlide);
    slides.forEach((slide, slideIndex) => {
      slide.classList.toggle("is-active", slideIndex === currentIndex);
    });
    if (currentNumber) currentNumber.textContent = formatNumber(currentIndex + 1);
    if (totalNumber) totalNumber.textContent = formatNumber(slides.length);
    if (status && announce) status.textContent = `Gambar ${currentIndex + 1} dari ${slides.length}`;
    loadSlideImage(slides[(currentIndex + 1) % slides.length]);
    if (scheduleNext) scheduleAutoplay();
  }

  previousButton.addEventListener("click", () => showSlide(currentIndex - 1));
  nextButton.addEventListener("click", () => showSlide(currentIndex + 1));
  toggleButton.addEventListener("click", () => {
    autoplayPaused = !autoplayPaused;
    updateAutoplayControl();
    scheduleAutoplay();
  });
  slideshow.addEventListener("pointerenter", (event) => {
    if (event.pointerType === "touch") return;
    pointerOver = true;
    scheduleAutoplay();
  });
  slideshow.addEventListener("pointerleave", (event) => {
    if (event.pointerType === "touch") return;
    pointerOver = false;
    scheduleAutoplay();
  });
  document.addEventListener("visibilitychange", scheduleAutoplay);
  slideshow.addEventListener("keydown", (event) => {
    if (event.key === "ArrowLeft") {
      event.preventDefault();
      showSlide(currentIndex - 1);
    } else if (event.key === "ArrowRight") {
      event.preventDefault();
      showSlide(currentIndex + 1);
    }
  });
  showSlide(currentIndex, false, false);
  updateAutoplayControl();
  controls.hidden = false;
  scheduleAutoplay();
}

initHeroSlideshow();

const galleryDialog = document.querySelector("#gallery-lightbox");
const lightboxImage = document.querySelector("#lightbox-image");
const lightboxCaption = document.querySelector("#lightbox-caption");
const lightboxClose = document.querySelector(".lightbox-close");
const galleryGrid = document.querySelector("#gallery-grid");

function closeLightbox() {
  if (galleryDialog?.open) galleryDialog.close();
}

function openLightbox(item) {
  if (!galleryDialog || !lightboxImage || !lightboxCaption) return;
  lightboxImage.src = item.dataset.galleryImage;
  lightboxImage.alt = item.dataset.galleryAlt || item.querySelector("img")?.alt || "Foto galeri sekolah";
  lightboxCaption.textContent = item.dataset.galleryCaption || "Galeri sekolah";
  galleryDialog.showModal();
}

galleryGrid?.addEventListener("click", (event) => {
  const item = event.target.closest("[data-gallery-image]");
  if (item) openLightbox(item);
});
lightboxClose?.addEventListener("click", closeLightbox);
galleryDialog?.addEventListener("click", (event) => {
  if (event.target === galleryDialog) closeLightbox();
});

function escapeHTML(value) {
  return String(value ?? "").replace(/[&<>"']/g, (character) => ({
    "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;",
  })[character]);
}

function safeLink(value, fallback = "#") {
  const url = String(value ?? "").trim();
  if (/^(https?:\/\/|mailto:|tel:|\/|[a-zA-Z0-9_-]+\.php)/i.test(url)) return url;
  return fallback;
}

function safeImage(value, fallback = "") {
  const url = String(value ?? "").trim();
  if (/^(https?:\/\/|\/|public\/|uploads\/)/i.test(url)) return url;
  return fallback;
}

function setCMSValue(key, value) {
  if (value === null || value === undefined || value === "") return;
  document.querySelectorAll(`[data-cms="${CSS.escape(key)}"]`).forEach((node) => {
    node.textContent = String(value);
  });
}

function applySettings(settings = {}) {
  const textKeys = [
    "brand_short", "brand_tagline", "hero_kicker", "hero_title", "hero_subtitle",
    "hero_highlight", "hero_description", "location_text", "about_title", "about_highlight",
    "about_lead", "about_description", "majors_title", "majors_highlight", "majors_description",
    "dkv_title", "dkv_description", "tkr_title", "tkr_description", "school_name", "school_address", "contact_address",
    "fact_dkv_code", "fact_dkv_title", "fact_dkv_detail", "fact_tkr_code", "fact_tkr_title", "fact_tkr_detail",
    "fact_student_count", "fact_student_title", "fact_student_detail", "fact_teacher_count", "fact_teacher_title", "fact_teacher_detail",
  ];
  textKeys.forEach((key) => setCMSValue(key, settings[key]));

  const themeMap = {
    theme_primary: "--green",
    theme_accent: "--gold",
    theme_soft: "--green-soft",
    theme_dark: "--green-dark",
  };
  Object.entries(themeMap).forEach(([key, token]) => {
    let color = String(settings[key] ?? "");
    if (key === "theme_primary" && color.toLowerCase() === "#0e4a3b") color = "#0A3F2F";
    if (/^#[\da-f]{6}$/i.test(color)) {
      document.documentElement.style.setProperty(token, color);
      if (key === "theme_primary") {
        const themeColor = document.querySelector('meta[name="theme-color"]');
        if (themeColor) themeColor.content = color;
      }
    }
  });

  const imageKeys = ["logo_image", "hero_image", "hero_image_2", "hero_image_3", "hero_image_4", "about_image", "dkv_image", "tkr_image"];
  imageKeys.forEach((key) => {
    const image = document.querySelector(`[data-cms-image="${key}"]`);
    if (!image || !settings[key]) return;
    const fallback = image.getAttribute("src") || image.dataset.heroSrc || "";
    const source = safeImage(settings[key], fallback);
    if (source) image.src = source;
  });
  const customLogo = safeImage(settings.logo_image, "");
  document.querySelectorAll(".brand-mark").forEach((mark) => {
    const logo = mark.querySelector('[data-cms-image="logo_image"]');
    if (!logo) return;
    logo.hidden = !customLogo;
    mark.classList.toggle("has-logo", Boolean(customLogo));
  });

  if (settings.site_title) document.title = settings.site_title;
  if (settings.meta_description) {
    const description = document.querySelector('[data-cms-meta="description"]');
    if (description) description.content = settings.meta_description;
  }
  if (/^https:\/\//i.test(String(settings.site_url || ""))) {
    const canonical = document.querySelector('link[rel="canonical"]');
    let canonicalBase = String(settings.site_url);
    while (canonicalBase.endsWith("/")) canonicalBase = canonicalBase.slice(0, -1);
    if (canonical) canonical.href = `${canonicalBase}/`;
  }

  if (/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(String(settings.contact_email || ""))) {
    const email = String(settings.contact_email).trim();
    document.querySelectorAll('a[href^="mailto:"]').forEach((link) => {
      const oldHref = link.getAttribute("href") || "";
      const queryAt = oldHref.indexOf("?");
      const query = queryAt >= 0 ? oldHref.slice(queryAt) : "";
      link.href = `mailto:${email}${query}`;
    });
    document.querySelectorAll("[data-cms-email]").forEach((link) => {
      const arrow = link.querySelector('[aria-hidden="true"]');
      link.textContent = email;
      if (arrow) link.append(arrow);
    });
  }

  const socialUrls = {
    instagram: settings.instagram_url,
    facebook: settings.facebook_url,
    youtube: settings.youtube_url,
  };
  Object.entries(socialUrls).forEach(([network, url]) => {
    if (!url) return;
    document.querySelectorAll(`[data-social="${network}"]`).forEach((link) => {
      link.href = safeLink(url, link.href);
    });
  });

  if (settings.section_visibility && typeof settings.section_visibility === "object") {
    Object.entries(settings.section_visibility).forEach(([section, visible]) => {
      document.querySelectorAll(`[data-cms-section="${CSS.escape(section)}"]`).forEach((node) => {
        node.classList.toggle("cms-hidden", visible === false || visible === "0");
      });
    });
  }
}

function renderTeachers(items = []) {
  const grid = document.querySelector("#teacher-grid");
  if (!grid || !Array.isArray(items) || items.length === 0) return;
  grid.innerHTML = items.map((teacher) => {
    const name = escapeHTML(teacher.name);
    const role = escapeHTML(teacher.role || teacher.department || "Tenaga Pendidik");
    const specialty = escapeHTML(teacher.specialty || teacher.qualification || "");
    const bio = escapeHTML(teacher.bio || "");
    const photo = escapeHTML(safeImage(teacher.photo, "public/images/lab-kolaborasi.jpg"));
    const badge = escapeHTML(teacher.department || "GURU");
    return `<article class="teacher-card"><div class="teacher-photo"><img src="${photo}" alt="${name}" loading="lazy"><span class="teacher-dept">${badge}</span></div><div class="teacher-info"><p>${role}</p><h3>${name}</h3><span>${specialty}${bio ? ` · ${bio}` : ""}</span></div></article>`;
  }).join("");
}

function renderNews(posts = []) {
  const grid = document.querySelector("#news-grid");
  if (!grid || !Array.isArray(posts) || posts.length === 0) return;
  grid.innerHTML = posts.slice(0, 3).map((post) => {
    const title = escapeHTML(post.title);
    const category = escapeHTML(post.category || "INFO SEKOLAH");
    const excerpt = escapeHTML(post.excerpt || "");
    const slug = encodeURIComponent(post.slug || "");
    const href = escapeHTML(safeLink(post.url || `post.php?slug=${slug}`, "#berita"));
    const image = escapeHTML(safeImage(post.cover_image || post.image, "public/images/kampus-hero.jpg"));
    const dateLabel = escapeHTML(post.date_label || post.published_at || "INFO SEKOLAH");
    return `<article class="news-card"><a class="news-image" href="${href}" aria-label="Baca: ${title}"><img src="${image}" alt="${title}" loading="lazy"><span class="news-category">${category}</span></a><div class="news-content"><p class="news-meta">${dateLabel}</p><h3><a href="${href}">${title}</a></h3><p>${excerpt}</p><a class="news-link" href="${href}">Baca informasi <span aria-hidden="true">→</span></a></div></article>`;
  }).join("");
}

function renderGallery(items = []) {
  if (!galleryGrid || !Array.isArray(items) || items.length === 0) return;
  galleryGrid.innerHTML = items.slice(0, 8).map((item) => {
    const image = escapeHTML(safeImage(item.image || item.media_url, "public/images/kampus-hero.jpg"));
    const caption = escapeHTML(item.caption || "Kegiatan sekolah");
    const alt = escapeHTML(item.alt_text || item.caption || "Foto kegiatan sekolah");
    return `<button class="gallery-item" type="button" data-gallery-image="${image}" data-gallery-caption="${caption}" data-gallery-alt="${alt}" aria-label="Perbesar foto: ${caption}"><img src="${image}" alt="${alt}" loading="lazy"><span class="gallery-plus" aria-hidden="true">+</span></button>`;
  }).join("");
}

function renderVideo(videos = [], settings = {}) {
  const latest = Array.isArray(videos) ? videos[0] : null;
  const thumbnail = document.querySelector("#latest-video-thumbnail");
  const playLink = document.querySelector("#latest-video-link");
  const titleNode = document.querySelector("#latest-video-title");
  const descriptionNode = document.querySelector("#latest-video-description");
  const channelLink = document.querySelector("#youtube-channel-link");

  if (channelLink && settings.youtube_url) channelLink.href = safeLink(settings.youtube_url, channelLink.href);
  if (playLink && latest?.youtube_id) {
    const id = String(latest.youtube_id).trim();
    if (/^[\w-]{11}$/.test(id)) {
      playLink.dataset.videoId = id;
      playLink.href = `https://www.youtube.com/watch?v=${encodeURIComponent(id)}`;
      playLink.setAttribute("aria-label", `Tonton video ${latest.title || "sekolah"} di YouTube`);
      if (thumbnail) {
        thumbnail.src = safeImage(latest.thumbnail, `https://i.ytimg.com/vi/${id}/hqdefault.jpg`);
        thumbnail.alt = escapeHTML(latest.title || "Video sekolah");
      }
      if (titleNode && latest.title) titleNode.textContent = latest.title;
      if (descriptionNode && latest.description) descriptionNode.textContent = latest.description;
    }
  }
}

function applySchoolContent(content) {
  if (!content || typeof content !== "object") return;
  applySettings(content.settings || {});
  renderTeachers(content.teachers || []);
  renderNews(content.posts || []);
  renderGallery(content.gallery || []);
  renderVideo(content.videos || [], content.settings || {});
}

async function loadSchoolContent() {
  if (window.SCHOOL_CMS_DATA) {
    applySchoolContent(window.SCHOOL_CMS_DATA);
    return;
  }
  try {
    const response = await fetch("api/home.php", { headers: { Accept: "application/json" }, cache: "no-store" });
    if (!response.ok) return;
    applySchoolContent(await response.json());
  } catch {
    // Static preview has no PHP endpoint; keep its included sample content.
  }
}

const videoThumb = document.querySelector(".video-thumb");
videoThumb?.addEventListener("click", (event) => {
  const play = event.target.closest("#latest-video-link");
  const videoId = play?.dataset.videoId;
  if (!play || !videoId || !/^[\w-]{11}$/.test(videoId)) return;
  event.preventDefault();
  const title = document.querySelector("#latest-video-title")?.textContent || "Video sekolah";
  videoThumb.innerHTML = `<iframe src="https://www.youtube-nocookie.com/embed/${encodeURIComponent(videoId)}?autoplay=1" title="${escapeHTML(title)}" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" referrerpolicy="strict-origin-when-cross-origin" allowfullscreen></iframe>`;
});

loadSchoolContent();
