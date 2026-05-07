// assets/js/src/components/OrderDetailView.jsx
import React, { useState, useEffect, useCallback } from 'react';
import ExportButtons from './ExportButtons';

const HARDWARE_DETAIL_STATUS = 'hardware-skus';

const formatOrderDetailTitle = (status) => {
	if (status === HARDWARE_DETAIL_STATUS) {
		return 'Hardware SKU breakdown';
	}
	const words = String(status || '')
		.replace(/^wc-/, '')
		.split('-')
		.filter(Boolean)
		.map((w) => w.charAt(0).toUpperCase() + w.slice(1));
	return `${words.join(' ')} orders`;
};

const OrderDetailView = ({ status, dates, onClose }) => {
	const [orders, setOrders] = useState([]);
	const [skuRows, setSkuRows] = useState([]);
	const [totalUnits, setTotalUnits] = useState(0);
	const [currentPage, setCurrentPage] = useState(1);
	const [loading, setLoading] = useState(true);
	const [error, setError] = useState(null);
	const [totalOrders, setTotalOrders] = useState(0);
	const itemsPerPage = 20;

	const isHardware = status === HARDWARE_DETAIL_STATUS;

	useEffect(() => {
		setCurrentPage(1);
	}, [status, dates.startDate, dates.endDate]);

	const fetchOrders = useCallback(
		async (page) => {
			setLoading(true);
			setError(null);

			try {
				if (!npcReportData?.isAdmin) {
					throw new Error('You do not have permission to access this data.');
				}

				const response = await fetch(
					`${npcReportData.root}npc-report/v1/orders?` +
						`status=${encodeURIComponent(status)}` +
						`&start_date=${encodeURIComponent(dates.startDate)}` +
						`&end_date=${encodeURIComponent(dates.endDate)}` +
						`&page=${page}` +
						`&per_page=${itemsPerPage}`,
					{
						method: 'GET',
						headers: {
							'X-WP-Nonce': npcReportData.nonce,
							'Content-Type': 'application/json'
						},
						credentials: 'same-origin'
					}
				);

				if (!response.ok) {
					throw new Error(`Failed to fetch orders: ${response.status} ${response.statusText}`);
				}

				const data = await response.json();
				setOrders(data.orders || []);
				setTotalOrders(parseInt(data.total, 10) || 0);
				setSkuRows([]);
				setTotalUnits(0);
			} catch (err) {
				console.error('Error fetching orders:', err);
				setError(err.message);
			} finally {
				setLoading(false);
			}
		},
		[status, dates.startDate, dates.endDate, itemsPerPage]
	);

	const fetchHardwareSkus = useCallback(
		async (page) => {
			setLoading(true);
			setError(null);

			try {
				if (!npcReportData?.isAdmin) {
					throw new Error('You do not have permission to access this data.');
				}

				const response = await fetch(
					`${npcReportData.root}npc-report/v1/hardware-skus?` +
						`start_date=${encodeURIComponent(dates.startDate)}` +
						`&end_date=${encodeURIComponent(dates.endDate)}` +
						`&page=${page}` +
						`&per_page=${itemsPerPage}`,
					{
						method: 'GET',
						headers: {
							'X-WP-Nonce': npcReportData.nonce,
							'Content-Type': 'application/json'
						},
						credentials: 'same-origin'
					}
				);

				if (!response.ok) {
					throw new Error(`Failed to fetch SKU breakdown: ${response.status} ${response.statusText}`);
				}

				const data = await response.json();
				setSkuRows(data.items || []);
				setTotalOrders(parseInt(data.total, 10) || 0);
				setTotalUnits(parseInt(data.total_units, 10) || 0);
				setOrders([]);
			} catch (err) {
				console.error('Error fetching hardware SKUs:', err);
				setError(err.message);
			} finally {
				setLoading(false);
			}
		},
		[dates.startDate, dates.endDate, itemsPerPage]
	);

	useEffect(() => {
		if (!status || !dates?.startDate || !dates?.endDate) {
			return;
		}
		if (isHardware) {
			fetchHardwareSkus(currentPage);
		} else {
			fetchOrders(currentPage);
		}
	}, [status, dates.startDate, dates.endDate, currentPage, isHardware, fetchOrders, fetchHardwareSkus]);

	const totalPages = Math.ceil(totalOrders / itemsPerPage);

	const handlePageChange = (newPage) => {
		setCurrentPage(newPage);
	};

	const detailTitle = formatOrderDetailTitle(status);

	return (
		<div className="order-detail-modal">
			<div className="order-detail-content">
				<div className="order-detail-header">
					<h2>{detailTitle}</h2>
					<div className="header-actions">
						<ExportButtons
							data={isHardware ? skuRows : orders}
							filename={
								isHardware
									? `hardware_skus_${dates.startDate}_${dates.endDate}`
									: `${status}_orders_${dates.startDate}_${dates.endDate}`
							}
							exportType={isHardware ? 'skus' : 'orders'}
						/>
						<button type="button" className="close-button" onClick={onClose}>
							&times;
						</button>
					</div>
				</div>

				<div className="date-range-info">
					<p>
						Date range: {dates.startDate} to {dates.endDate}
					</p>
					{isHardware ? (
						<>
							<p>Distinct SKUs: {totalOrders}</p>
							<p>Total units sold: {totalUnits}</p>
						</>
					) : (
						<p>
							Total orders: {totalOrders}
						</p>
					)}
				</div>

				{loading && <div className="loading-state">Loading…</div>}

				{error && <div className="error-state">Error: {error}</div>}

				{!loading && !error && isHardware && skuRows.length > 0 ? (
					<>
						<table className="wp-list-table widefat fixed striped">
							<thead>
								<tr>
									<th>SKU</th>
									<th>Product</th>
									<th>Units sold</th>
								</tr>
							</thead>
							<tbody>
								{skuRows.map((row) => (
									<tr key={row.sku}>
										<td>{row.sku}</td>
										<td>{row.product_name}</td>
										<td>{row.units_sold}</td>
									</tr>
								))}
							</tbody>
						</table>

						{totalPages > 1 && (
							<div className="tablenav">
								<div className="tablenav-pages">
									<span className="displaying-num">{totalOrders} SKUs</span>
									<span className="pagination-links">
										<button
											type="button"
											className="button"
											onClick={() => handlePageChange(1)}
											disabled={currentPage === 1}
										>
											«
										</button>
										<button
											type="button"
											className="button"
											onClick={() => handlePageChange(currentPage - 1)}
											disabled={currentPage === 1}
										>
											‹
										</button>
										<span className="paging-input">
											<span className="tablenav-paging-text">
												{currentPage} of {totalPages}
											</span>
										</span>
										<button
											type="button"
											className="button"
											onClick={() => handlePageChange(currentPage + 1)}
											disabled={currentPage === totalPages}
										>
											›
										</button>
										<button
											type="button"
											className="button"
											onClick={() => handlePageChange(totalPages)}
											disabled={currentPage === totalPages}
										>
											»
										</button>
									</span>
								</div>
							</div>
						)}
					</>
				) : null}

				{!loading && !error && !isHardware && orders.length > 0 ? (
					<>
						<table className="wp-list-table widefat fixed striped">
							<thead>
								<tr>
									<th>Order #</th>
									<th>Date</th>
									<th>Customer</th>
									<th>Status</th>
									<th>Tax</th>
									<th>Total</th>
									<th>Actions</th>
								</tr>
							</thead>
							<tbody>
								{orders.map((order) => (
									<tr key={order.id}>
										<td>#{order.id}</td>
										<td>
											{order.date ? new Date(order.date).toLocaleDateString() : 'N/A'}
										</td>
										<td>{order.customer}</td>
										<td>{order.status}</td>
										<td>${parseFloat(order.tax).toFixed(2)}</td>
										<td>${parseFloat(order.total).toFixed(2)}</td>
										<td>
											<a
												href={`/wp-admin/post.php?post=${order.id}&action=edit`}
												target="_blank"
												rel="noopener noreferrer"
											>
												View Order
											</a>
										</td>
									</tr>
								))}
							</tbody>
						</table>

						{totalPages > 1 && (
							<div className="tablenav">
								<div className="tablenav-pages">
									<span className="displaying-num">{totalOrders} items</span>
									<span className="pagination-links">
										<button
											type="button"
											className="button"
											onClick={() => handlePageChange(1)}
											disabled={currentPage === 1}
										>
											«
										</button>
										<button
											type="button"
											className="button"
											onClick={() => handlePageChange(currentPage - 1)}
											disabled={currentPage === 1}
										>
											‹
										</button>
										<span className="paging-input">
											<span className="tablenav-paging-text">
												{currentPage} of {totalPages}
											</span>
										</span>
										<button
											type="button"
											className="button"
											onClick={() => handlePageChange(currentPage + 1)}
											disabled={currentPage === totalPages}
										>
											›
										</button>
										<button
											type="button"
											className="button"
											onClick={() => handlePageChange(totalPages)}
											disabled={currentPage === totalPages}
										>
											»
										</button>
									</span>
								</div>
							</div>
						)}
					</>
				) : null}

				{!loading && !error && isHardware && skuRows.length === 0 ? (
					<div className="no-orders-message">No SKU line items found for the selected date range.</div>
				) : null}

				{!loading && !error && !isHardware && orders.length === 0 ? (
					<div className="no-orders-message">No orders found for the selected date range.</div>
				) : null}
			</div>
		</div>
	);
};

export default React.memo(OrderDetailView);
