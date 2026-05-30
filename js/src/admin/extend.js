import Extend from 'flarum/common/extenders';
import ZaiBotSettingsPanel from './components/ZaiBotSettingsPanel';

export default [
  new Extend.Admin().page(ZaiBotSettingsPanel),
];
