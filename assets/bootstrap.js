import { startStimulusApp } from '@symfony/stimulus-bundle';
import LiveController from './controllers/live_controller.js';

// Démarre Stimulus en lui donnant l'URL du module courant
// pour qu'il puisse aussi charger automatiquement les autres contrôleurs
const app = startStimulusApp(import.meta.url);

// Enregistrement manuel du contrôleur "live" (tableau de bord Live)
app.register('live', LiveController);
