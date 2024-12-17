const path = require('path');
const TerserPlugin = require('terser-webpack-plugin');
const MiniCssExtractPlugin = require('mini-css-extract-plugin');
const { CleanWebpackPlugin } = require('clean-webpack-plugin');

module.exports = (env, argv) => {
	const isProduction = argv.mode === 'production';

	return {
		entry: './assets/js/src/index.js',
		output: {
			path: path.resolve(__dirname, 'assets/js/dist'),
			filename: 'bundle.js',
			clean: true
		},
		module: {
			rules: [
				{
					test: /\.(js|jsx)$/,
					exclude: /node_modules/,
					use: {
						loader: 'babel-loader',
						options: {
							presets: ['@babel/preset-react']
						}
					}
				},
				{
					test: /\.scss$/,
					use: [
						isProduction ? MiniCssExtractPlugin.loader : 'style-loader',
						'css-loader',
						{
							loader: 'sass-loader',
							options: {
								sourceMap: !isProduction
							}
						}
					]
				}
			]
		},
		resolve: {
			extensions: ['*', '.js', '.jsx']
		},
		plugins: [
			new CleanWebpackPlugin(),
			new MiniCssExtractPlugin({
				filename: '../../css/dist/[name].css'
			})
		],
		optimization: {
			minimizer: [
				new TerserPlugin({
					terserOptions: {
						compress: {
							drop_console: isProduction
						}
					}
				})
			]
		},
		devtool: isProduction ? false : 'source-map',
		performance: {
			hints: isProduction ? 'warning' : false
		}
	};
};