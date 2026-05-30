import app from 'flarum/forum/app';

app.initializers.add('zephyrisle/flarum-zai-bot', () => {
  const forum = app.forum;
  const storeData = app.store?.data;

  if (!forum || !storeData) {
    return;
  }

  storeData.zaiBot = {
    allowAiMentions: forum.attribute?.('zaiBotAllowAiMentions') ?? false,
    developerMode: forum.attribute?.('zaiBotDeveloperMode') ?? false,
  };
});
