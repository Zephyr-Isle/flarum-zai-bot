import Extend from 'flarum/common/extenders';
import ZaiBotSettingsPanel from './components/ZaiBotSettingsPanel';

export default [
  new Extend.Admin().setting(
    () =>
      function () {
        return <ZaiBotSettingsPanel page={this} />;
      },
    -100
  ),
];
