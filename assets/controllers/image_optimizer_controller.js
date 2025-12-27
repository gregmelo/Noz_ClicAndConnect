import { Controller } from "@hotwired/stimulus";

export default class extends Controller {
    static targets = ["input", "preview", "status"];
    static values = {
        maxWidth: { type: Number, default: 800 },
        quality: { type: Number, default: 0.8 }
    }

    optimize(event) {
        const file = event.target.files[0];
        if (!file || !file.type.startsWith('image/')) return;

        // Visual feedback
        if (this.hasStatusTarget) {
            this.statusTarget.textContent = "Optimisation en cours...";
            this.statusTarget.classList.remove('hidden');
        }

        const reader = new FileReader();
        reader.onload = (e) => {
            const img = new Image();
            img.onload = () => {
                const canvas = document.createElement('canvas');
                let width = img.width;
                let height = img.height;

                // Calculate scales
                if (width > this.maxWidthValue) {
                    height *= this.maxWidthValue / width;
                    width = this.maxWidthValue;
                }

                canvas.width = width;
                canvas.height = height;

                const ctx = canvas.getContext('2d');
                ctx.drawImage(img, 0, 0, width, height);

                // Convert to Blob
                canvas.toBlob((blob) => {
                    const optimizedFile = new File([blob], file.name, {
                        type: 'image/jpeg',
                        lastModified: Date.now(),
                    });

                    // Update input using DataTransfer
                    const dataTransfer = new DataTransfer();
                    dataTransfer.items.add(optimizedFile);
                    this.inputTarget.files = dataTransfer.files;

                    // Update preview if available
                    if (this.hasPreviewTarget) {
                        this.previewTarget.src = URL.createObjectURL(blob);
                        this.previewTarget.classList.remove('hidden');
                    }

                    if (this.hasStatusTarget) {
                        const savedKB = Math.round((file.size - blob.size) / 1024);
                        this.statusTarget.textContent = `Optimisé ! (-${savedKB} KB)`;
                        setTimeout(() => this.statusTarget.classList.add('hidden'), 3000);
                    }
                }, 'image/jpeg', this.qualityValue);
            };
            img.src = e.target.result;
        };
        reader.readAsDataURL(file);
    }
}
