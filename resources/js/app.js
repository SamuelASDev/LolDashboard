import Alpine from 'alpinejs';
import { createSyncProgress } from './sync-progress';

Alpine.data('syncProgress', createSyncProgress);
window.Alpine = Alpine;
Alpine.start();
