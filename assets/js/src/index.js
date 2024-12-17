import React from 'react';
import { createRoot } from 'react-dom/client';
import App from './components/App';
import '../../css/src/main.scss';

const container = document.getElementById('npc-report-admin');
const root = createRoot(container);
root.render(<App />);
