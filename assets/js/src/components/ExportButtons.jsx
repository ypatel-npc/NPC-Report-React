import React from 'react';

const ExportButtons = ({ data, filename, exportType = 'orders' }) => {
	const exportToCSV = () => {
		let headers;
		let csvData;

		if (exportType === 'skus') {
			headers = ['SKU', 'Product', 'Units sold'];
			csvData = (data || []).map((row) => [row.sku, row.product_name, row.units_sold]);
		} else {
			headers = ['Order #', 'Date', 'Customer', 'Status', 'Tax', 'Total'];
			csvData = (data || []).map((order) => [
				order.id,
				order.date,
				order.customer,
				order.status,
				order.tax,
				order.total
			]);
		}

		const csvContent = [headers.join(','), ...csvData.map((row) => row.join(','))].join('\n');

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
			<button type="button" onClick={exportToCSV} className="export-button">
				Export to CSV
			</button>
		</div>
	);
};

export default ExportButtons;
