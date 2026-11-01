'use strict';
const routeSelect = document.querySelector('#route_id');
const distanceInput = document.querySelector('#custom_distance');
const routeInfo = document.querySelector('#route-info');
function updateRoute() {
  const option = routeSelect?.selectedOptions[0];
  if (!option?.dataset.distance) {
    if (routeInfo) routeInfo.textContent = 'Відстань і коефіцієнт підставляються з довідника маршрутів.';
    if (distanceInput) distanceInput.placeholder = 'Відстань обраного маршруту';
    return;
  }
  distanceInput.placeholder = `${option.dataset.distance} км за маршрутом`;
  routeInfo.textContent = `${option.dataset.distance} км · коефіцієнт ${option.dataset.factor} · збір ${option.dataset.fee} грн`;
}
routeSelect?.addEventListener('change', updateRoute);
updateRoute();
document.querySelectorAll('form[data-confirm]').forEach(form => {
  form.addEventListener('submit', event => {
    if (!window.confirm(form.dataset.confirm)) event.preventDefault();
  });
});
document.querySelector('#print-button')?.addEventListener('click', () => window.print());
