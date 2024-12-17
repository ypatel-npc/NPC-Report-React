import React from 'react';

const ExportButtons = ({ data, filename }) => {
    const exportToCSV = () => {
        // Define headers for CSV
        const headers = [
            'Order #',
            'Date',
            'Customer',
            'Status',
			'Tax',
            'Total',
        ];

        // Format the data for CSV
        const csvData = data.map(order => [
			order.id,
			order.date,
			order.customer,
            order.status,
            order.tax,
            order.total
            // order.items.map(item => `${item.name} (${item.quantity})`).join('; ')
        ]);

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

export default ExportButtons;
