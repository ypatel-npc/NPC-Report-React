import React from 'react';

const StatsCard = ({ title, value, icon, color, onClick, status }) => {
    const handleClick = () => {
        console.log('Card clicked with status:', status);
        onClick(status);
    };

    return (
        <div 
            className="stats-card" 
            onClick={handleClick}
			style={{ cursor: 'pointer', backgroundColor: color }}
        >
            <div className="stats-icon" style={{ backgroundColor: color }}>
                <span className={`dashicons ${icon}`}></span>
            </div>
            <div className="stats-content">
                <h3>{title}</h3>
                <p>{value}</p>
            </div>
        </div>
    );
};

export default StatsCard; 