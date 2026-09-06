(function () {
  "use strict";

  document.querySelectorAll("[data-image-input]").forEach((input) => {
    input.addEventListener("change", () => {
      const file = input.files?.[0];
      const preview = document.getElementById(input.dataset.previewTarget);
      if (!file || !preview) return;
      if (!(["image/jpeg", "image/png"].includes(file.type)) || file.size > 500 * 1024) {
        input.value = "";
        window.showDashboardToast?.("Choose a JPG or PNG image no larger than 500KB.");
        return;
      }
      const reader = new FileReader();
      reader.addEventListener("load", () => {
        preview.replaceChildren();
        const image = document.createElement("img");
        image.src = reader.result;
        image.alt = "Selected logo preview";
        preview.append(image);
      });
      reader.readAsDataURL(file);
    });
  });

  // DEMO UI FEEDBACK ONLY — replace with PHP form submission later.
  document.querySelectorAll("[data-demo-form]").forEach((form) => {
    form.addEventListener("submit", (event) => {
      event.preventDefault();
      if (!form.reportValidity()) return;
      window.showDashboardToast?.(form.dataset.successMessage || "Changes saved successfully.");
    });
  });
})();
