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
  ];
  textKeys.forEach((key) => setCMSValue(key, settings[key]));

  const themeMap = {
    theme_primary: "--green",
    theme_accent: "--gold",
    theme_soft: "--green-soft",
    theme_dark: "--green-dark",
  };
  Object.entries(themeMap).forEach(([key, token]) => {
    if (/^#[\da-f]{6}$/i.test(String(settings[key] ?? ""))) {
      document.documentElement.style.setProperty(token, settings[key]);
    }
  });

  const imageKeys = ["hero_image", "about_image", "dkv_image", "tkr_image"];
  imageKeys.forEach((key) => {
    const image = document.querySelector(`[data-cms-image="${key}"]`);
    if (image && settings[key]) image.src = safeImage(settings[key], image.src);
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
