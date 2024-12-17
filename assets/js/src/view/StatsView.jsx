import React from 'react';
import { useParams, useLocation, useNavigate } from 'react-router-dom';
import OrderDetailView from '../components/OrderDetailView';

const StatsView = () => {
    const { status } = useParams();
    const location = useLocation();
    const navigate = useNavigate();
    const { startDate, endDate } = location.state || {};

    const handleClose = () => {
        navigate('/');
    };

    return (
        <OrderDetailView
            status={status}
            startDate={startDate}
            endDate={endDate}
            onClose={handleClose}
        />
    );
};

export default StatsView;
