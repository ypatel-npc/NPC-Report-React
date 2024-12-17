import React from 'react';
import { BrowserRouter, Routes, Route } from 'react-router-dom';
import Dashboard from '../view/Dashboard';
import StatsView from '../view/StatsView';

const App = () => {
	return (
		<BrowserRouter basename="/wp-admin/admin.php">
			<Routes>
				<Route path="/" element={<Dashboard />} />
				<Route path="/stats/:status" element={<StatsView />} />
			</Routes>
		</BrowserRouter>
	);
};

export default App;