document.addEventListener('DOMContentLoaded', function () {
    const mainImage = document.getElementById('productMainImage');
    if (!mainImage) {
        return;
    }

    const thumbs = document.querySelectorAll('.product-thumb-btn');
    thumbs.forEach((btn) => {
        btn.addEventListener('click', function () {
            const src = btn.getAttribute('data-src');
            if (!src) {
                return;
            }

            mainImage.setAttribute('src', src);
            thumbs.forEach((el) => el.classList.remove('is-active'));
            btn.classList.add('is-active');
        });
    });
});
