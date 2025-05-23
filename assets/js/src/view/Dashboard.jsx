import React, { useState, useEffect, useCallback } from 'react';
import StatsCard from '../components/StatsCard';
import DateRangePicker, { getDefaultDateRange } from '../components/DateRangePicker';
import OrderDetailView from '../components/OrderDetailView';
import DashboardExportButton from '../components/DashboardExportButton';
import Select from 'react-select';

const Dashboard = () => {
	const [stats, setStats] = useState([]);
	const [loading, setLoading] = useState(true);
	const [error, setError] = useState(null);
	const [selectedStatus, setSelectedStatus] = useState(null);
	const [dateRange, setDateRange] = useState(getDefaultDateRange());
	const [selectedFilters, setSelectedFilters] = useState([]);
	// Add these console logs
	// console.log('Stats:', stats);
	// console.log('Selected Filters:', selectedFilters);
	// Fetch data when date changes
	const fetchDashboardStats = async (dates) => {
		setLoading(true);
		setError(null);

		try {
			const response = await fetch(
				`${npcReportData.root}npc-report/v1/stats?start_date=${dates.startDate}&end_date=${dates.endDate}`,
				{
					method: 'GET',
					headers: {
						'X-WP-Nonce': npcReportData.nonce,
						'Content-Type': 'application/json'
					},
					credentials: 'same-origin'
				}
			);

			if (!response.ok) throw new Error(`Failed to fetch stats: ${response.statusText}`);

			const data = await response.json();
			setStats(Array.isArray(data) ? data : []);
		} catch (err) {
			setError(err.message);
		} finally {
			setLoading(false);
		}
	};

	// Fetch on initial load and whenever date changes
	useEffect(() => {
		fetchDashboardStats(dateRange);
	}, [dateRange]);

	const handleDateChange = useCallback((newDates) => {
		setDateRange(newDates);
	}, []);

	// Create options with proper handling of undefined values
	const filterOptions = stats ? stats.map(stat => ({
		value: stat.status || stat.title, // Use title as fallback if status is undefined
		label: stat.title
	})) : [];

	

	return (
		<div className="wrap">
			<div className="dashboard-header">
				<h1>Dashboard Overview</h1>
				{!loading && !error && stats.length > 0 && (
					<DashboardExportButton
						data={stats}
						filename={`dashboard_stats_${dateRange.startDate}_${dateRange.endDate}`}
						type="dashboard"
					/>
				)}
			</div>

			<DateRangePicker onDateChange={handleDateChange} />

			<div className="filter-section">
				<Select
					isMulti
					value={selectedFilters}
					onChange={(selected) => setSelectedFilters(selected || [])}
					options={filterOptions}
					className="basic-multi-select"
					classNamePrefix="select"
					placeholder="Select Filters..."
				/>
			</div>

			{loading && <div className="loading-state">Loading dashboard data...</div>}
			{error && <div className="error-state">Error: {error}</div>}

			{!loading && !error && (
				<div className="stats-grid">
					{stats
						.filter(stat => {
							if (selectedFilters.length === 0) return true;
							
							return selectedFilters.some(filter => 
								filter.value === (stat.status || stat.title)
							);
						})
						.map((stat, index) => (
							<StatsCard
								key={`stat-${index}-${stat.title}`}
								{...stat}
								onClick={() => setSelectedStatus(stat.status)}
							/>
						))}
				</div>
			)}

			{selectedStatus && (
				<OrderDetailView
					status={selectedStatus}
					dates={dateRange}
					onClose={() => setSelectedStatus(null)}
				/>
			)}
		</div>
	);
};

export default Dashboard;
