'use strict';

const path = require('path');
const webpack = require('webpack');
const MiniCssExtractPlugin = require('mini-css-extract-plugin');
const { VueLoaderPlugin } = require('vue-loader');
const UglifyJsPlugin = require('uglifyjs-webpack-plugin');

const ENV_PROD = (process.env.NODE_ENV === 'production');

let config = {
    context: __dirname,
    entry: {
        index: './src/Resources/js/index.js',
        admin: './src/Resources/js/admin.js',
        payment: './src/Resources/js/payment.js',
        doc: './src/Resources/js/doc.js',
        wallet: './src/Resources/js/wallet/wallet.js',
    },
    output: {
        publicPath: '/inc',
        path: __dirname + '/public/inc',
        filename: '[name].js'
    },
    resolve: {
        extensions: ['.js', '.vue'],
        alias: {
            'vue$': 'vue/dist/vue.esm.js'
        }
    },
    module: {
        rules: [
            {
                test: /\.js$/,
                loader: 'babel-loader',
                exclude: '/node_modules/',
                query: {
                    presets: [
                        require.resolve('@babel/preset-env')
                    ]
                }
            },
            {
                test: /\.(scss|sass|css)$/,
                use: [
                    "vue-style-loader",
                    {
                        loader: MiniCssExtractPlugin.loader,
                        options: {},
                    },
                    "css-loader",
                    {
                        loader: 'postcss-loader',
                        options: {
                            plugins: () => [
                                require('autoprefixer')({'browsers': ['> 1%', 'last 2 versions']}),
                                require('precss')(),
                            ],
                        }
                    },
                ]
            },
            {
                test: /(\.png|\.svg)$/,
                loader: 'file?name=/img/[name].[ext]?[hash:8]'
            },
            {
                test: /\.vue$/,
                loader: 'vue-loader'
            }
        ]
    },
    plugins: [
        new MiniCssExtractPlugin('app.css', {
            allChunks: true
        }),
        new VueLoaderPlugin(),
        new webpack.ProvidePlugin({
            timezoneJs: 'timezone-js/src/date.js',
            timezoneJsData: 'tzdata/tzdata.js',
            Main: path.resolve(path.join(__dirname, 'src/Resources/js/Main.js')),
        })
    ],
    devtool: '#eval-source-map',
    node: {
        fs: 'empty'
    },
};

if (ENV_PROD) {
    config.devtool = '#source-map';
    config.plugins = (config.plugins || []).concat([
        new webpack.DefinePlugin({
            'process.env': {
                NODE_ENV: '"production"'
            }
        }),
        new webpack.LoaderOptionsPlugin({
            minimize: true
        }),
        new UglifyJsPlugin({
            sourceMap: true
        })
    ]);
}

module.exports = config;
