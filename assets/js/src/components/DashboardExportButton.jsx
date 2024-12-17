import React from 'react';

const DashboardExportButton = ({ data, filename, type = 'orders' }) => {
	const exportToCSV = () => {
		let headers, csvData;

		if (type === 'orders') {
			headers = ['Order #', 'Date', 'Customer', 'Total', 'Items'];
			csvData = data.map(order => [
				order.order_number,
				order.date_created,
				order.customer_name,
				order.total,
				order.items.map(item => `${item.name} (${item.quantity})`).join('; ')
			]);
		} else if (type === 'dashboard') {
			headers = ['Title', 'Value', 'Status'];
			csvData = data.map(stat => [
				stat.title,
				stat.value,
				stat.status
			]);
		}

		// Combine headers and data
		const csvContent = [
			headers.join(','),
			...csvData.map(row => row.join(','))
		].join('\n');

		// Create and trigger download
		const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
		const link = document.createElement('a');
		const url = URL.createObjectURL(blob);
		link.setAttribute('href', url);
		link.setAttribute('download', `${filename}.csv`);
		link.style.visibility = 'hidden';
		document.body.appendChild(link);
		link.click();
		document.body.removeChild(link);
	};

	return (
		<div className="export-buttons">
			<button
				onClick={exportToCSV}
				className="export-button"
			>
				Export to CSV
			</button>
		</div>
	);
};

export default DashboardExportButton;