// assets/js/src/components/OrderDetailView.jsx
import React, { useState, useEffect } from 'react';
import ExportButtons from './ExportButtons';

const OrderDetailView = ({ status, dates, onClose }) => {
	const [orders, setOrders] = useState([]);
	const [currentPage, setCurrentPage] = useState(1);
	const [loading, setLoading] = useState(true);
	const [error, setError] = useState(null);
	const [totalOrders, setTotalOrders] = useState(0);
	const itemsPerPage = 20;

	// Fetch orders when component mounts or when status/dates change
	useEffect(() => {
		fetchOrders(currentPage);
	}, [status, dates, currentPage]);

	const fetchOrders = async (page) => {
		setLoading(true);
		setError(null);

		try {
			if (!npcReportData?.isAdmin) {
				throw new Error('You do not have permission to access this data.');
			}

			const response = await fetch(
				`${npcReportData.root}npc-report/v1/orders?` + 
				`status=${status}` +
				`&start_date=${dates.startDate}` +
				`&end_date=${dates.endDate}` +
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
			setOrders(data.orders);
			setTotalOrders(parseInt(data.total) || 0);
		} catch (err) {
			console.error('Error fetching orders:', err);
			setError(err.message);
		} finally {
			setLoading(false);
		}
	};

	const totalPages = Math.ceil(totalOrders / itemsPerPage);

	// Handle page change
	const handlePageChange = (newPage) => {
		setCurrentPage(newPage);
	};

	return (
		<div className="order-detail-modal">
			<div className="order-detail-content">
				<div className="order-detail-header">
					<h2>{status.charAt(0).toUpperCase() + status.slice(1)} Orders</h2>
					<div className="header-actions">
						<ExportButtons 
							data={orders} 
							filename={`${status}_orders_${dates.startDate}_${dates.endDate}`}
						/>
						<button className="close-button" onClick={onClose}>&times;</button>
					</div>

				</div>

				<div className="date-range-info">
					<p>Date Range: {dates.startDate} to {dates.endDate}</p>
					<p>Total {status} orders: {totalOrders}</p>
				</div>

				{loading && <div className="loading-state">Loading orders...</div>}

				{error && <div className="error-state">Error: {error}</div>}

				{!loading && !error && orders.length > 0 ? (
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
									{/* <th>Items</th> */}
									<th>Actions</th>
								</tr>
							</thead>
							<tbody>
								{orders.map(order => (
									console.log(order),
									<tr key={order.id}>
										{/* Order Number */}
										<td>#{order.id}</td>

										{/* Date */}
										<td>
											{order.date
												? new Date(order.date).toLocaleDateString()
												: 'N/A'}
										</td>

										{/* Customer (Placeholder if not available) */}
										<td>
											{order.customer}
										</td>

										{/* Status */}
										<td>{order.status}</td>

										{/* Tax */}
										<td>${parseFloat(order.tax).toFixed(2)}</td>

										{/* Total */}
										<td>${parseFloat(order.total).toFixed(2)}</td>

										{/* Line Items */}
										{/* <td>
											{order.line_items.length > 0 ? (
												order.line_items.map((item, index) => (
													<span key={item.id}>
														{item.name} ({item.quantity})
														{index < order.line_items.length - 1 ? ', ' : ''}
													</span>
												))
											) : (
												<span>No items</span>
											)}
										</td> */}

										{/* Action Link */}
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

						{/* Pagination */}
						{totalPages > 1 && (
							<div className="tablenav">
								<div className="tablenav-pages">
									<span className="displaying-num">
										{totalOrders} items
									</span>
									<span className="pagination-links">
										<button
											className="button"
											onClick={() => handlePageChange(1)}
											disabled={currentPage === 1}
										>
											«
										</button>
										<button
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
											className="button"
											onClick={() => handlePageChange(currentPage + 1)}
											disabled={currentPage === totalPages}
										>
											›
										</button>
										<button
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
				) : (
					!loading && !error && (
						<div className="no-orders-message">
							No {status} orders found for the selected date range.
						</div>
					)
				)}
			</div>
		</div>
	);
};

export default React.memo(OrderDetailView);