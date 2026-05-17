import app from 'flarum/admin/app';
import { extend } from './src/admin';

app.initializers.add('zephyrisle/flarum-zai-bot', () => {
  extend.forEach((extender) => extender.extend(app));
});
