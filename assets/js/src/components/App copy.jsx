import React, { useState, useEffect } from 'react';
import StatsCard from './StatsCard';
import DateRangePicker from './DateRangePicker';
import OrderDetailView from './OrderDetailView';
import DashboardExportButton from './DashboardExportButton';

const App = () => {
	const [stats, setStats] = useState([]);
	const [loading, setLoading] = useState(true);
	const [error, setError] = useState(null);
	const [selectedStatus, setSelectedStatus] = useState(null);

	// Add date state
	const [startDate, setStartDate] = useState(() => {
		// Default to 30 days ago
		const date = new Date();
		date.setDate(date.getDate() - 30);
		return date.toISOString().split('T')[0];
	});

	const [endDate, setEndDate] = useState(() => {
		// Default to today
		return new Date().toISOString().split('T')[0];
	});

	useEffect(() => {
		console.log('Dates changed, fetching new data...');
		fetchDashboardStats();
	}, [startDate, endDate]);

	const fetchDashboardStats = async () => {
		console.log('Fetching stats...');
		try {
			if (!myReactPluginData.isAdmin) {
				throw new Error('You do not have permission to access this data.');
			}

			const response = await fetch(
				`${myReactPluginData.root}npc-report/v1/stats?start_date=${startDate}&end_date=${endDate}`,
				{
					method: 'GET',
					headers: {
						'X-WP-Nonce': myReactPluginData.nonce,
						'Content-Type': 'application/json'
					},
					credentials: 'same-origin'
				}
			);

			if (!response.ok) {
				throw new Error(`Failed to fetch stats: ${response.status} ${response.statusText}`);
			}

			const data = await response.json();
			console.log('Received stats data:', data);
			data.forEach(stat => {
				console.log(`Stat ${stat.title} has status: ${stat.status}`);
			});
			setStats(data);
		} catch (err) {
			console.error('Error fetching stats:', err);
			setError(err.message);
		} finally {
			setLoading(false);
		}
	};

	const handleCardClick = (status) => {
		console.log('handleCardClick called with status:', status);
		setSelectedStatus(status);
	};

	useEffect(() => {
		console.log('selectedStatus changed to:', selectedStatus);
	}, [selectedStatus]);

	return (
		<div className="wrap">
			<div className="dashboard-header">
				<h1>Dashboard Overview</h1>
				{!loading && !error && (
					<DashboardExportButton 
						data={stats} 
						filename={`dashboard_stats_${startDate}_${endDate}`}
						type="dashboard"
					/>
				)}
			</div>

			<DateRangePicker
				startDate={startDate}
				endDate={endDate}
				onStartDateChange={setStartDate}
				onEndDateChange={setEndDate}
			/>

			{loading && <div>Loading dashboard data...</div>}

			{error && <div className="error">Error: {error}</div>}

			{!loading && !error && (
				<div className="stats-grid">
					{stats.map((stat, index) => (
						<StatsCard
							key={index}
							title={stat.title}
							value={stat.value}
							icon={stat.icon}
							color={stat.color}
							status={stat.status}
							onClick={handleCardClick}
						/>
					))}
				</div>
			)}

			{selectedStatus && (
				<OrderDetailView
					status={selectedStatus}
					startDate={startDate}
					endDate={endDate}
					onClose={() => setSelectedStatus(null)}
				/>
			)}
		</div>
	);
};

export default App;