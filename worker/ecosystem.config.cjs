module.exports = {
	apps: [{
		name: 'pmg-worker',
		script: 'dist/index.js',
		cwd: __dirname,
		restart_delay: 5000,       // 5s wait before restart
		max_restarts: 50,          // max restarts in a window
		min_uptime: 10000,         // consider started after 10s
		autorestart: true,
		watch: false,
		env: {
			NODE_ENV: 'production',
		},
	}],
};
