const navToggle = document.getElementById('navToggle');
const mainNav = document.getElementById('mainNav');
if (navToggle && mainNav) navToggle.addEventListener('click', () => {
  const isOpen = mainNav.classList.toggle('open');
  navToggle.setAttribute('aria-expanded', String(isOpen));
});

const profileToggle = document.getElementById('profileToggle');
const profileDropdown = document.getElementById('profileDropdown');
if (profileToggle && profileDropdown) {
  profileToggle.addEventListener('click', () => {
    const isOpen = profileToggle.getAttribute('aria-expanded') === 'true';
    profileToggle.setAttribute('aria-expanded', String(!isOpen));
    profileDropdown.hidden = isOpen;
  });
  document.addEventListener('click', event => {
    if (!profileToggle.contains(event.target) && !profileDropdown.contains(event.target)) {
      profileToggle.setAttribute('aria-expanded', 'false');
      profileDropdown.hidden = true;
    }
  });
  document.addEventListener('keydown', event => {
    if (event.key === 'Escape') {
      profileToggle.setAttribute('aria-expanded', 'false');
      profileDropdown.hidden = true;
    }
  });
}

document.querySelectorAll('[data-expand-toggle]').forEach(button => {
  const panel = document.getElementById(button.dataset.expandToggle);
  if (!panel) return;
  button.addEventListener('click', () => {
    const expanded = button.getAttribute('aria-expanded') === 'true';
    button.setAttribute('aria-expanded', String(!expanded));
    panel.hidden = expanded;
    const label = button.querySelector('[data-toggle-label]');
    if (label) label.textContent = expanded ? button.dataset.closeLabel : button.dataset.openLabel;
    if (!expanded) panel.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
  });
});

const search = document.getElementById('dressSearch');
const results = document.getElementById('searchResults');
let searchTimer;
let activeSearchController;
if (search && results) {
  const tableBody = document.getElementById('dressTableBody');
  const cardView = document.getElementById('dressCardView');
  const emptyState = document.getElementById('noDressResults');
  const managerView = results.dataset.canManage === 'true';
  const csrfToken = results.dataset.csrfToken;
  document.querySelectorAll('[data-dress-view]').forEach(button => {
    button.addEventListener('click', () => {
      const showCards = button.dataset.dressView === 'cards';
      results.classList.toggle('show-cards', showCards);
      document.querySelectorAll('[data-dress-view]').forEach(viewButton => {
        const active = viewButton === button;
        viewButton.classList.toggle('active', active);
        viewButton.setAttribute('aria-pressed', String(active));
      });
    });
  });
  const makeDeleteForm = dress => {
    const form = document.createElement('form');
    form.method = 'post';
    form.action = 'dress-delete.php';
    form.addEventListener('submit', event => {
      if (!confirm(`Delete ${dress.name}?`)) event.preventDefault();
    });
    const csrf = document.createElement('input');
    csrf.type = 'hidden';
    csrf.name = 'csrf_token';
    csrf.value = csrfToken;
    const dressId = document.createElement('input');
    dressId.type = 'hidden';
    dressId.name = 'dress_id';
    dressId.value = dress.dress_id;
    const button = document.createElement('button');
    button.type = 'submit';
    button.className = 'table-action danger';
    button.textContent = 'Delete';
    form.append(csrf,dressId,button);
    return form;
  };
  const makeLink = (href, label, className = 'table-action') => {
    const link = document.createElement('a');
    link.href = href;
    link.className = className;
    link.textContent = label;
    return link;
  };
  const makeCard = dress => {
    const article = document.createElement('article');
    article.className = 'dress-card';
    const visual = document.createElement('div');
    visual.className = 'dress-visual';
    if (dress.photo_url) {
      const image = document.createElement('img');
      image.src = dress.photo_url;
      image.alt = `${dress.name} wedding dress`;
      image.loading = 'lazy';
      visual.append(image);
    } else {
      const placeholder = document.createElement('div');
      placeholder.className = 'photo-placeholder';
      const name = document.createElement('strong');
      name.textContent = dress.name;
      const hint = document.createElement('span');
      hint.textContent = 'Add a dress photo';
      placeholder.append(name,hint);
      visual.append(placeholder);
    }
    const size = document.createElement('span');
    size.className = 'dress-size';
    size.textContent = dress.size;
    visual.append(size);
    const info = document.createElement('div');
    info.className = 'dress-info';
    const meta = document.createElement('div');
    meta.className = 'dress-meta';
    meta.textContent = `${dress.color} · Size ${dress.size}`;
    const heading = document.createElement('h3');
    heading.textContent = dress.name;
    const description = document.createElement('p');
    description.textContent = dress.description || '';
    const bottom = document.createElement('div');
    bottom.className = 'card-bottom';
    const price = document.createElement('strong');
    price.textContent = `${Number(dress.rental_price).toLocaleString()} RWF`;
    const status = document.createElement('span');
    status.className = Number(dress.available) ? 'available' : 'unavailable';
    status.textContent = Number(dress.available) ? 'Available to rent' : 'Rented';
    bottom.append(price,status);
    const actions = document.createElement('div');
    actions.className = 'card-actions';
    actions.append(makeLink(`dress.php?id=${encodeURIComponent(dress.dress_id)}`, 'View dress', 'button full'));
    if (managerView) {
      actions.append(makeLink(`dresses.php?edit=${encodeURIComponent(dress.dress_id)}`, 'Edit', 'button secondary'));
      const deleteForm = makeDeleteForm(dress);
      deleteForm.querySelector('button').className = 'button danger';
      actions.append(deleteForm);
    }
    info.append(meta,heading,description,bottom,actions);
    article.append(visual,info);
    return article;
  };
  const makeTableRow = dress => {
    const row = document.createElement('tr');
    const nameCell = document.createElement('td');
    nameCell.className = 'dress-table-name';
    if (dress.photo_url) {
      const image = document.createElement('img');
      image.src = dress.photo_url;
      image.alt = '';
      image.loading = 'lazy';
      nameCell.append(image);
    } else {
      const placeholder = document.createElement('span');
      placeholder.className = 'table-photo-placeholder';
      const icon = document.createElement('i');
      icon.className = 'bi bi-image';
      icon.setAttribute('aria-hidden', 'true');
      placeholder.append(icon);
      nameCell.append(placeholder);
    }
    const nameInfo = document.createElement('span');
    const name = document.createElement('strong');
    name.textContent = dress.name;
    const description = document.createElement('small');
    description.textContent = dress.description || '';
    nameInfo.append(name,description);
    nameCell.append(nameInfo);
    const size = document.createElement('td');
    size.textContent = dress.size;
    const color = document.createElement('td');
    color.textContent = dress.color;
    const price = document.createElement('td');
    price.className = 'dress-table-price';
    price.textContent = `${Number(dress.rental_price).toLocaleString()} RWF`;
    const availability = document.createElement('td');
    const badge = document.createElement('span');
    badge.className = `availability-badge ${Number(dress.available) ? 'is-available' : 'is-unavailable'}`;
    badge.textContent = Number(dress.available) ? 'Available' : 'Rented';
    availability.append(badge);
    const actionsCell = document.createElement('td');
    const actions = document.createElement('div');
    actions.className = 'table-actions';
    actions.append(makeLink(`dress.php?id=${encodeURIComponent(dress.dress_id)}`, 'View'));
    if (managerView) {
      actions.append(makeLink(`dresses.php?edit=${encodeURIComponent(dress.dress_id)}`, 'Edit'));
      actions.append(makeDeleteForm(dress));
    }
    actionsCell.append(actions);
    row.append(nameCell,size,color,price,availability,actionsCell);
    return row;
  };
  search.addEventListener('input', () => {
    clearTimeout(searchTimer);
    if (activeSearchController) activeSearchController.abort();
    searchTimer = setTimeout(async () => {
      const controller = new AbortController();
      activeSearchController = controller;
      try {
        const response = await fetch(`api-search.php?q=${encodeURIComponent(search.value)}`, {
          headers: {'Accept':'application/json'},
          signal: controller.signal
        });
        if (!response.ok) throw new Error('Search failed');
        const dresses = await response.json();
        if (tableBody) tableBody.replaceChildren(...dresses.map(makeTableRow));
        cardView.replaceChildren(...dresses.map(makeCard));
        emptyState.hidden = dresses.length > 0;
        if (!dresses.length) {
          emptyState.textContent = 'No matching dresses found.';
          return;
        }
      } catch (error) {
        if (error.name !== 'AbortError') {
          emptyState.textContent = 'Unable to search right now. Please try again.';
          emptyState.hidden = false;
        }
      }
    }, 250);
  });
}

document.querySelectorAll('.rent-form').forEach(form => {
  form.addEventListener('submit', event => {
    const start = form.querySelector('[name="start_date"]').value;
    const end = form.querySelector('[name="end_date"]').value;
    if (start && end && end < start) {
      event.preventDefault();
      alert('The end date must be on or after the start date.');
    }
  });
});

const dressPhotoInput = document.getElementById('dressPhotos');
const dressPhotoPreview = document.getElementById('dressPhotoPreview');
if (dressPhotoInput && dressPhotoPreview) {
  const savedPhotos = Array.from(dressPhotoPreview.querySelectorAll('.photo-preview-item'));
  let selectedFiles = [];
  let objectUrls = [];
  const renderPhotos = () => {
    objectUrls.forEach(url => URL.revokeObjectURL(url));
    objectUrls = [];
    const previews = selectedFiles.map((file, index) => {
      const figure = document.createElement('figure');
      figure.className = 'photo-preview-item selected-photo';
      const image = document.createElement('img');
      const url = URL.createObjectURL(file);
      objectUrls.push(url);
      image.src = url;
      image.alt = `Selected wedding dress photo: ${file.name}`;
      const caption = document.createElement('figcaption');
      caption.textContent = file.name;
      const remove = document.createElement('button');
      remove.type = 'button';
      remove.className = 'photo-remove';
      remove.setAttribute('aria-label', `Remove ${file.name}`);
      remove.title = 'Remove this photo';
      remove.innerHTML = '&times;';
      remove.addEventListener('click', () => {
        selectedFiles.splice(index, 1);
        updateFileInput();
        renderPhotos();
      });
      figure.append(image, caption, remove);
      return figure;
    });
    const allPreviews = [...savedPhotos, ...previews];
    if (!allPreviews.length) {
      const empty = document.createElement('span');
      empty.className = 'photo-preview-empty';
      empty.textContent = 'Choose photos to preview them here.';
      allPreviews.push(empty);
    }
    dressPhotoPreview.replaceChildren(...allPreviews);
  };
  const updateFileInput = () => {
    const transfer = new DataTransfer();
    selectedFiles.forEach(file => transfer.items.add(file));
    dressPhotoInput.files = transfer.files;
  };
  dressPhotoInput.addEventListener('change', () => {
    const newFiles = Array.from(dressPhotoInput.files || []);
    const files = [...selectedFiles, ...newFiles];
    const totalSize = files.reduce((total, file) => total + file.size, 0);
    if (files.length > 10 || totalSize > 30 * 1024 * 1024 || files.some(file => !['image/jpeg','image/png','image/webp'].includes(file.type) || file.size > 5 * 1024 * 1024)) {
      updateFileInput();
      alert('Choose up to 10 JPG, PNG or WebP photos, each under 5 MB and 30 MB combined.');
      return;
    }
    selectedFiles = files;
    updateFileInput();
    renderPhotos();
  });
}

const carousel = document.getElementById('homeCarousel');
if (carousel) {
  const slides = Array.from(carousel.querySelectorAll('.carousel-slide'));
  const dots = Array.from(carousel.querySelectorAll('.carousel-dots button'));
  let activeIndex = slides.findIndex(slide => slide.classList.contains('active'));
  let timer;
  const showSlide = index => {
    if (!slides.length) return;
    activeIndex = (index + slides.length) % slides.length;
    slides.forEach((slide, slideIndex) => {
      const active = slideIndex === activeIndex;
      slide.hidden = !active;
      slide.classList.toggle('active', active);
    });
    dots.forEach((dot, dotIndex) => {
      const active = dotIndex === activeIndex;
      dot.classList.toggle('active', active);
      dot.setAttribute('aria-current', active ? 'true' : 'false');
    });
  };
  carousel.querySelector('.carousel-control.previous')?.addEventListener('click', () => showSlide(activeIndex - 1));
  carousel.querySelector('.carousel-control.next')?.addEventListener('click', () => showSlide(activeIndex + 1));
  dots.forEach((dot, index) => dot.addEventListener('click', () => showSlide(index)));
  const startCarousel = () => {
    if (slides.length > 1 && !window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
      timer = window.setInterval(() => showSlide(activeIndex + 1), 5000);
    }
  };
  carousel.addEventListener('mouseenter', () => window.clearInterval(timer));
  carousel.addEventListener('mouseleave', startCarousel);
  carousel.addEventListener('focusin', () => window.clearInterval(timer));
  carousel.addEventListener('focusout', startCarousel);
  startCarousel();
}

document.querySelectorAll('.product-thumbnails button').forEach(button => {
  button.addEventListener('click', () => {
    const selectedImage = document.getElementById('selectedDressImage');
    if (!selectedImage) return;
    selectedImage.src = button.dataset.photo;
    document.querySelectorAll('.product-thumbnails button').forEach(item => item.classList.toggle('active', item === button));
  });
});
