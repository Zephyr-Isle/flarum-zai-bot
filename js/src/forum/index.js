import app from 'flarum/forum/app';

app.initializers.add('zephyrisle/flarum-zai-bot', () => {
  app.store.data.zaiBot = {
    allowAiMentions: app.forum.attribute('zaiBotAllowAiMentions'),
    developerMode: app.forum.attribute('zaiBotDeveloperMode'),
  };
});
