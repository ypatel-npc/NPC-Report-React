import React, { useState, useCallback } from 'react';
import PropTypes from 'prop-types';

// Centralized Date Utility Logic
export const getDefaultDateRange = (daysBack = 30) => {
	const today = new Date();
	const pastDate = new Date();
	pastDate.setDate(today.getDate() - daysBack);

	return {
		startDate: pastDate.toISOString().split('T')[0],
		endDate: today.toISOString().split('T')[0]
	};
};

const DateRangePicker = ({ onDateChange }) => {
	const initialDates = getDefaultDateRange(); // 30 days back by default

	const [dates, setDates] = useState(initialDates);

	// Handle changes for date inputs without triggering API
	const handleDateChange = useCallback((type, value) => {
		setDates((prev) => {
			const newDates = { ...prev, [type]: value };
			return newDates;
		});
	}, []);

	// Submit handler that triggers the API call
	const handleSubmit = useCallback((e) => {
		e.preventDefault();
		onDateChange(dates);
	}, [dates, onDateChange]);

	return (
		<form className="date-range-picker" onSubmit={handleSubmit}>
			<div className="date-inputs">
				<div className="date-input-group">
					<label htmlFor="start-date">From:</label>
					<input
						type="date"
						id="start-date"
						value={dates.startDate}
						onChange={(e) => handleDateChange('startDate', e.target.value)}
						max={dates.endDate} // Constraint
					/>
				</div>
				<div className="date-input-group">
					<label htmlFor="end-date">To:</label>
					<input
						type="date"
						id="end-date"
						value={dates.endDate}
						onChange={(e) => handleDateChange('endDate', e.target.value)}
						min={dates.startDate} // Constraint
						max={new Date().toISOString().split('T')[0]} // Cannot exceed today
					/>
				</div>
				<button 
					type="submit" 
					className="button button-primary"
				>
					Apply Date Range
				</button>
			</div>
		</form>
	);
};

DateRangePicker.propTypes = {
	onDateChange: PropTypes.func.isRequired
};

export default React.memo(DateRangePicker);
