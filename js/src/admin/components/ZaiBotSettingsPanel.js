import app from 'flarum/admin/app';
import ExtensionPage from 'flarum/admin/components/ExtensionPage';
import m from 'mithril';
import settingsSchema from '../utils/settingsSchema';

const PROVIDER_DRIVERS = [
  { value: 'openai-compatible', labelKey: 'provider.drivers.openai_compatible', baseUrl: 'https://api.openai.com/v1' },
  { value: 'moonshot', labelKey: 'provider.drivers.moonshot', baseUrl: 'https://api.moonshot.cn/v1' },
  { value: 'glm', labelKey: 'provider.drivers.glm', baseUrl: 'https://open.bigmodel.cn/api/paas/v4' },
  { value: 'minimax', labelKey: 'provider.drivers.minimax', baseUrl: 'https://api.minimax.chat/v1' },
  { value: 'qwen', labelKey: 'provider.drivers.qwen', baseUrl: 'https://dashscope.aliyuncs.com/compatible-mode/v1' },
  { value: 'deepseek', labelKey: 'provider.drivers.deepseek', baseUrl: 'https://api.deepseek.com/v1' },
  { value: 'anthropic', labelKey: 'provider.drivers.anthropic', baseUrl: 'https://api.anthropic.com/v1' },
  { value: 'google', labelKey: 'provider.drivers.google', baseUrl: 'https://generativelanguage.googleapis.com/v1beta' },
  { value: 'openrouter', labelKey: 'provider.drivers.openrouter', baseUrl: 'https://openrouter.ai/api/v1' },
];

export default class ZaiBotSettingsPanel extends ExtensionPage {
  oninit(vnode) {
    super.oninit(vnode);

    this.activeTab = 'api';
    this.loadingAdminData = false;
    this.adminDataError = null;
    this.dashboard = null;
    this.providers = [];
    this.agents = [];
    this.providerSaving = false;
    this.providerTesting = false;
    this.providerModelLoading = false;
    this.providerActionResult = null;
    this.agentSaving = false;
    this.maintenanceLoading = false;
    this.maintenanceResult = null;
    this.testingChat = false;
    this.testChatResult = null;
    this.providerForm = this.emptyProviderForm();
    this.agentForm = this.emptyAgentForm();
    this.testChatForm = {
      agentId: '',
      message: '',
    };

    this.loadAdminData();
  }

  translator(key, replacements = {}) {
    return app.translator.trans(`zai-bot.admin.${key}`, replacements, true);
  }

  ui(key, replacements = {}) {
    return this.translator(`ui.${key}`, replacements);
  }

  setting(key, fallback = '', label) {
    return super.setting(`zai-bot.${key}`, fallback, label);
  }

  tabs() {
    return settingsSchema(this.translator.bind(this));
  }

  extensionEnabled(extensionId) {
    const extensions = globalThis.flarum?.extensions || {};

    return Boolean(extensions[extensionId]);
  }

  emptyProviderForm() {
    return {
      id: null,
      name: '',
      driver: 'openai-compatible',
      baseUrl: 'https://api.openai.com/v1',
      apiKey: '',
      modelsText: '[]',
      isActive: true,
    };
  }

  resetProviderForm() {
    this.providerForm = this.emptyProviderForm();
    this.providerActionResult = null;
  }

  providerDriverMeta(driver) {
    return PROVIDER_DRIVERS.find((item) => item.value === driver) || PROVIDER_DRIVERS[0];
  }

  providerDriverLabel(driver) {
    return this.ui(this.providerDriverMeta(driver).labelKey);
  }

  applyProviderDriver(driver) {
    const previous = this.providerDriverMeta(this.providerForm.driver);
    const next = this.providerDriverMeta(driver);
    const currentBaseUrl = (this.providerForm.baseUrl || '').trim();

    this.providerForm.driver = next.value;

    if (!currentBaseUrl || currentBaseUrl === previous.baseUrl) {
      this.providerForm.baseUrl = next.baseUrl;
    }
  }

  formatModelsText(models) {
    return JSON.stringify(models || [], null, 2);
  }

  parseProviderModelsText() {
    const raw = this.providerForm.modelsText?.trim();

    if (!raw) {
      return [];
    }

    try {
      const parsed = JSON.parse(raw);

      if (Array.isArray(parsed)) {
        return parsed.filter((value) => typeof value === 'string' && value.trim()).map((value) => value.trim());
      }

      if (parsed && typeof parsed === 'object') {
        const normalized = { ...parsed };

        if (Array.isArray(normalized.available)) {
          normalized.available = normalized.available
            .filter((value) => typeof value === 'string' && value.trim())
            .map((value) => value.trim());
        }

        return normalized;
      }
    } catch (error) {
      const lines = raw
        .split(/\r\n|\r|\n/)
        .map((value) => value.trim())
        .filter(Boolean);

      if (lines.length > 0) {
        return lines;
      }
    }

    throw new Error(this.ui('provider.models_invalid'));
  }

  buildProviderPayload() {
    return {
      id: this.providerForm.id,
      name: this.providerForm.name?.trim(),
      driver: this.providerForm.driver,
      baseUrl: this.providerForm.baseUrl?.trim(),
      apiKey: this.providerForm.apiKey,
      models: this.parseProviderModelsText(),
      isActive: this.providerForm.isActive,
    };
  }

  providerModelCount(provider) {
    const models = provider?.models;

    if (Array.isArray(models)) {
      return models.length;
    }

    if (Array.isArray(models?.available)) {
      return models.available.length;
    }

    return [models?.chat, models?.embedding, models?.image, models?.vision].filter(Boolean).length;
  }

  mergeDiscoveredModels(models) {
    const current = this.parseProviderModelsText();
    const nextModels = Array.isArray(models) ? models : [];

    if (Array.isArray(current)) {
      return nextModels;
    }

    if (current && typeof current === 'object') {
      return {
        ...current,
        available: nextModels,
      };
    }

    return nextModels;
  }

  emptyAgentForm() {
    return {
      id: null,
      providerId: '',
      flarumUserId: '',
      name: '',
      username: '',
      email: '',
      personality: this.ui('agent.default_personality'),
      expertise: '',
      systemPrompt: '',
      temperature: 0.7,
      isActive: true,
      replyMode: 'mention',
      activeTagsText: '[]',
      chatModel: '',
      embeddingModel: '',
      language: 'zh-CN',
    };
  }

  async apiRequest(path, options = {}) {
    return app.request({
      url: `/api${path}`,
      ...options,
    });
  }

  extractRequestError(error, fallback) {
    return error?.response?.errors?.[0]?.detail || error?.response?.message || error?.message || fallback;
  }

  async loadAdminData() {
    this.loadingAdminData = true;
    this.adminDataError = null;
    m.redraw();

    try {
      const [dashboard, providers, agents] = await Promise.all([
        this.apiRequest('/zai-bot/admin/dashboard'),
        this.apiRequest('/zai-bot/admin/providers'),
        this.apiRequest('/zai-bot/admin/agents'),
      ]);

      this.dashboard = dashboard;
      this.providers = providers.data || [];
      this.agents = agents.data || [];
    } catch (error) {
      this.adminDataError = this.extractRequestError(error, this.ui('errors.load_admin_data'));
    } finally {
      this.loadingAdminData = false;
      m.redraw();
    }
  }

  editProvider(provider) {
    this.providerForm = {
      id: provider.id,
      name: provider.name || '',
      driver: this.providerDriverMeta(provider.driver || 'openai-compatible').value,
      baseUrl: provider.baseUrl || this.providerDriverMeta(provider.driver || 'openai-compatible').baseUrl,
      apiKey: '',
      modelsText: this.formatModelsText(provider.models || []),
      isActive: Boolean(provider.isActive),
    };
    this.providerActionResult = null;
  }

  async saveProvider() {
    this.providerSaving = true;
    this.adminDataError = null;
    this.providerActionResult = null;
    m.redraw();

    try {
      const body = this.buildProviderPayload();

      await this.apiRequest(
        this.providerForm.id
          ? `/zai-bot/admin/providers/${this.providerForm.id}`
          : '/zai-bot/admin/providers',
        {
          method: this.providerForm.id ? 'PUT' : 'POST',
          body,
        }
      );

      this.resetProviderForm();
      await this.loadAdminData();
    } catch (error) {
      this.adminDataError = this.extractRequestError(error, this.ui('errors.save_provider'));
    } finally {
      this.providerSaving = false;
      m.redraw();
    }
  }

  async deleteProvider(provider) {
    if (!confirm(this.ui('provider.delete_confirm', { name: provider.name }))) {
      return;
    }

    try {
      await this.apiRequest(`/zai-bot/admin/providers/${provider.id}`, {
        method: 'DELETE',
      });

      if (this.providerForm.id === provider.id) {
        this.resetProviderForm();
      }

      await this.loadAdminData();
    } catch (error) {
      this.adminDataError = this.extractRequestError(error, this.ui('errors.delete_provider'));
      m.redraw();
    }
  }

  async testProvider() {
    this.providerTesting = true;
    this.adminDataError = null;
    this.providerActionResult = null;
    m.redraw();

    try {
      const result = await this.apiRequest('/zai-bot/admin/providers/test', {
        method: 'POST',
        body: this.buildProviderPayload(),
      });

      this.providerActionResult = {
        type: 'success',
        title: this.ui('provider.test_success_title'),
        detail: this.ui('provider.test_success_detail', { count: result.modelCount || 0 }),
      };
    } catch (error) {
      this.providerActionResult = {
        type: 'error',
        title: this.ui('provider.test_failed_title'),
        detail: this.extractRequestError(error, this.ui('errors.test_provider')),
      };
    } finally {
      this.providerTesting = false;
      m.redraw();
    }
  }

  async discoverProviderModels() {
    this.providerModelLoading = true;
    this.adminDataError = null;
    this.providerActionResult = null;
    m.redraw();

    try {
      const result = await this.apiRequest('/zai-bot/admin/providers/discover-models', {
        method: 'POST',
        body: this.buildProviderPayload(),
      });

      this.providerForm.modelsText = this.formatModelsText(this.mergeDiscoveredModels(result.models || []));
      this.providerActionResult = {
        type: 'success',
        title: this.ui('provider.models_updated_title'),
        detail: this.ui('provider.models_updated_detail', { count: result.modelCount || 0 }),
      };
    } catch (error) {
      this.providerActionResult = {
        type: 'error',
        title: this.ui('provider.models_failed_title'),
        detail: this.extractRequestError(error, this.ui('errors.discover_models')),
      };
    } finally {
      this.providerModelLoading = false;
      m.redraw();
    }
  }

  editAgent(agent) {
    this.agentForm = {
      id: agent.id,
      providerId: agent.providerId || '',
      flarumUserId: agent.flarumUserId || '',
      name: agent.name || '',
      username: agent.user?.username || '',
      email: agent.user?.email || '',
      personality: agent.personality || '',
      expertise: agent.expertise || '',
      systemPrompt: agent.systemPrompt || '',
      temperature: agent.temperature ?? 0.7,
      isActive: Boolean(agent.isActive),
      replyMode: agent.replyMode || 'mention',
      activeTagsText: JSON.stringify(agent.activeTags || [], null, 2),
      chatModel: agent.chatModel || '',
      embeddingModel: agent.embeddingModel || '',
      language: agent.language || 'zh-CN',
    };
    this.testChatForm.agentId = agent.id;
  }

  async saveAgent() {
    this.agentSaving = true;
    this.adminDataError = null;
    m.redraw();

    try {
      const activeTags = this.agentForm.activeTagsText?.trim() ? JSON.parse(this.agentForm.activeTagsText) : [];
      const body = {
        providerId: this.agentForm.providerId || null,
        flarumUserId: this.agentForm.flarumUserId || null,
        name: this.agentForm.name,
        username: this.agentForm.username,
        email: this.agentForm.email,
        personality: this.agentForm.personality,
        expertise: this.agentForm.expertise,
        systemPrompt: this.agentForm.systemPrompt,
        temperature: Number(this.agentForm.temperature || 0.7),
        isActive: this.agentForm.isActive,
        replyMode: this.agentForm.replyMode,
        activeTags,
        chatModel: this.agentForm.chatModel,
        embeddingModel: this.agentForm.embeddingModel,
        language: this.agentForm.language,
      };

      await this.apiRequest(
        this.agentForm.id
          ? `/zai-bot/admin/agents/${this.agentForm.id}`
          : '/zai-bot/admin/agents',
        {
          method: this.agentForm.id ? 'PUT' : 'POST',
          body,
        }
      );

      this.agentForm = this.emptyAgentForm();
      await this.loadAdminData();
    } catch (error) {
      this.adminDataError = this.extractRequestError(error, this.ui('errors.save_agent'));
    } finally {
      this.agentSaving = false;
      m.redraw();
    }
  }

  async deleteAgent(agent) {
    if (!confirm(this.ui('agent.delete_confirm', { name: agent.name }))) {
      return;
    }

    try {
      await this.apiRequest(`/zai-bot/admin/agents/${agent.id}`, {
        method: 'DELETE',
      });

      if (this.agentForm.id === agent.id) {
        this.agentForm = this.emptyAgentForm();
      }

      await this.loadAdminData();
    } catch (error) {
      this.adminDataError = this.extractRequestError(error, this.ui('errors.delete_agent'));
      m.redraw();
    }
  }

  async runMaintenance(action) {
    this.maintenanceLoading = true;
    this.maintenanceResult = null;
    m.redraw();

    try {
      this.maintenanceResult = await this.apiRequest('/zai-bot/admin/maintenance', {
        method: 'POST',
        body: { action },
      });
    } catch (error) {
      this.maintenanceResult = {
        error: this.extractRequestError(error, this.ui('errors.maintenance')),
      };
    } finally {
      this.maintenanceLoading = false;
      m.redraw();
    }
  }

  async testChat() {
    this.testingChat = true;
    this.testChatResult = null;
    m.redraw();

    try {
      this.testChatResult = await this.apiRequest('/zai-bot/admin/test-chat', {
        method: 'POST',
        body: {
          agentId: this.testChatForm.agentId,
          message: this.testChatForm.message,
        },
      });
    } catch (error) {
      this.testChatResult = {
        error: this.extractRequestError(error, this.ui('errors.test_chat')),
      };
    } finally {
      this.testingChat = false;
      m.redraw();
    }
  }

  renderBoolean(field) {
    const key = `tabs.${this.activeTab}.fields.${field.key}`;
    const disabled = field.dependency && !this.extensionEnabled(field.dependency);

    return (
      <div className="Form-group">
        <label className="checkbox">
          <input type="checkbox" bidi={this.setting(field.key)} disabled={disabled} />
          {this.translator(`${key}.label`)}
        </label>
        <div className="helpText">
          {disabled
            ? this.translator('tools.dependency_missing', { extension: field.dependency })
            : this.translator(`${key}.help`)}
        </div>
      </div>
    );
  }

  renderInput(field) {
    const key = `tabs.${this.activeTab}.fields.${field.key}`;

    if (field.type === 'markup') {
      return <div className="ZaiBotSettingsPanel-note">{field.content}</div>;
    }

    if (field.type === 'boolean') {
      return this.renderBoolean(field);
    }

    if (field.type === 'textarea') {
      return (
        <div className="Form-group">
          <label>{this.translator(`${key}.label`)}</label>
          <div className="helpText">{this.translator(`${key}.help`)}</div>
          <textarea
            className="FormControl"
            bidi={this.setting(field.key)}
            rows={field.rows || 4}
            placeholder={this.translator(`${key}.placeholder`)}
          />
        </div>
      );
    }

    return (
      <div className="Form-group">
        <label>{this.translator(`${key}.label`)}</label>
        <div className="helpText">{this.translator(`${key}.help`)}</div>
        <input
          className="FormControl"
          type={field.type || 'text'}
          bidi={this.setting(field.key)}
          placeholder={this.translator(`${key}.placeholder`)}
          min={field.min}
          max={field.max}
          step={field.step}
        />
      </div>
    );
  }

  renderDashboard() {
    if (!this.dashboard?.stats) {
      return null;
    }

    const stats = this.dashboard.stats;

    return (
      <div className="ZaiBotSettingsPanel-metrics">
        {[
          ['providers', stats.providerCount],
          ['agents', stats.agentCount],
          ['active_agents', stats.activeAgentCount],
          ['memories', stats.memoryCount],
          ['actions', stats.actionCount],
          ['failed_actions', stats.failedActionCount],
          ['enabled_integrations', stats.enabledIntegrationCount],
          ['tool_ready_integrations', stats.toolReadyIntegrationCount],
        ].map(([key, value]) => (
          <div key={key} className="ZaiBotSettingsPanel-metric">
            <div className="ZaiBotSettingsPanel-metricLabel">{this.ui(`metrics.${key}`)}</div>
            <div className="ZaiBotSettingsPanel-metricValue">{value}</div>
          </div>
        ))}
      </div>
    );
  }

  renderIntegrations() {
    const integrations = this.dashboard?.integrations;

    if (!integrations?.catalog?.length) {
      return null;
    }

    const capabilities = Object.entries(integrations.summary?.capabilities || {});

    return (
      <div className="ZaiBotSettingsPanel-subsection">
        <div className="ZaiBotSettingsPanel-subsectionHeader">
          <h4>{this.ui('integrations.title')}</h4>
        </div>

        {capabilities.length > 0 && (
          <div className="ZaiBotSettingsPanel-note">
            <strong>{this.ui('integrations.enabled_capabilities')}</strong>{' '}
            {capabilities.map(([name, labels]) => `${name} (${labels.length})`).join(', ')}
          </div>
        )}

        <div className="ZaiBotSettingsPanel-list">
          {integrations.catalog.map((integration) => (
            <div key={integration.id} className="ZaiBotSettingsPanel-listItem">
              <div>
                <strong>{integration.label}</strong>
                <div className="helpText">
                  {integration.id} · {integration.group} · {integration.mode}
                </div>
                <div className="helpText">{integration.capabilities.join(', ')}</div>
              </div>
              <div className="ZaiBotSettingsPanel-actions">
                <span className={`Button Button--text ${integration.enabled ? 'ZaiBotSettingsPanel-status--success' : 'ZaiBotSettingsPanel-status--error'}`}>
                  {integration.enabled ? this.ui('common.enabled') : this.ui('common.disabled')}
                </span>
              </div>
            </div>
          ))}
        </div>
      </div>
    );
  }

  renderProviderManager() {
    const providerActionBusy = this.providerSaving || this.providerTesting || this.providerModelLoading;

    return (
      <div className="ZaiBotSettingsPanel-subsection">
        <div className="ZaiBotSettingsPanel-subsectionHeader">
          <h4>{this.ui('provider.section_title')}</h4>
          <button type="button" className="Button Button--text" onclick={() => this.resetProviderForm()}>
            {this.ui('common.new')}
          </button>
        </div>

        <div className="ZaiBotSettingsPanel-grid">
          <div className="Form-group">
            <label>{this.ui('provider.name')}</label>
            <input className="FormControl" value={this.providerForm.name} oninput={(e) => (this.providerForm.name = e.target.value)} />
          </div>
          <div className="Form-group">
            <label>{this.ui('provider.driver')}</label>
            <select className="FormControl" value={this.providerForm.driver} onchange={(e) => this.applyProviderDriver(e.target.value)}>
              {PROVIDER_DRIVERS.map((driver) => (
                <option key={driver.value} value={driver.value}>{this.providerDriverLabel(driver.value)}</option>
              ))}
            </select>
            <div className="helpText">{this.providerDriverMeta(this.providerForm.driver).baseUrl}</div>
          </div>
          <div className="Form-group ZaiBotSettingsPanel-gridSpan2">
            <label>{this.ui('provider.base_url')}</label>
            <input className="FormControl" value={this.providerForm.baseUrl} oninput={(e) => (this.providerForm.baseUrl = e.target.value)} />
          </div>
          <div className="Form-group ZaiBotSettingsPanel-gridSpan2">
            <label>{this.ui('provider.api_key')}</label>
            <input className="FormControl" type="password" value={this.providerForm.apiKey} oninput={(e) => (this.providerForm.apiKey = e.target.value)} />
            {this.providerForm.id && <div className="helpText">{this.ui('provider.keep_api_key_hint')}</div>}
          </div>
          <div className="Form-group ZaiBotSettingsPanel-gridSpan2">
            <label>{this.ui('provider.models')}</label>
            <textarea className="FormControl" rows="5" value={this.providerForm.modelsText} oninput={(e) => (this.providerForm.modelsText = e.target.value)} />
            <div className="helpText">{this.ui('provider.models_help')}</div>
          </div>
          <div className="Form-group">
            <label className="checkbox">
              <input type="checkbox" checked={this.providerForm.isActive} onchange={(e) => (this.providerForm.isActive = e.target.checked)} />
              {this.ui('common.active')}
            </label>
          </div>
        </div>

        <div className="Form-group ZaiBotSettingsPanel-actions">
          <button type="button" className="Button Button--secondary" disabled={providerActionBusy} onclick={() => this.testProvider()}>
            {this.providerTesting ? this.ui('common.testing') : this.ui('provider.test_connection')}
          </button>
          <button type="button" className="Button Button--secondary" disabled={providerActionBusy} onclick={() => this.discoverProviderModels()}>
            {this.providerModelLoading ? this.ui('provider.fetching_models') : this.ui('provider.fetch_models')}
          </button>
          <button type="button" className="Button Button--primary" disabled={providerActionBusy} onclick={() => this.saveProvider()}>
            {this.providerSaving ? this.ui('common.saving') : this.providerForm.id ? this.ui('provider.update') : this.ui('provider.create')}
          </button>
        </div>

        {this.providerActionResult && (
          <div className={`ZaiBotSettingsPanel-note ${this.providerActionResult.type === 'error' ? 'ZaiBotSettingsPanel-status--error' : 'ZaiBotSettingsPanel-status--success'}`}>
            <strong>{this.providerActionResult.title}</strong>
            <div>{this.providerActionResult.detail}</div>
          </div>
        )}

        <div className="ZaiBotSettingsPanel-list">
          {this.providers.map((provider) => (
            <div key={provider.id} className="ZaiBotSettingsPanel-listItem">
              <div>
                <strong>{provider.name}</strong>
                <div className="helpText">{this.providerDriverLabel(provider.driver)} · {provider.baseUrl}</div>
                <div className="helpText">
                  {this.ui('provider.models_count', { count: this.providerModelCount(provider) })} · {provider.apiKeyConfigured ? this.ui('provider.api_key_configured') : this.ui('provider.api_key_missing')}
                </div>
              </div>
              <div className="ZaiBotSettingsPanel-actions">
                <button type="button" className="Button Button--text" onclick={() => this.editProvider(provider)}>{this.ui('common.edit')}</button>
                <button type="button" className="Button Button--text" onclick={() => this.deleteProvider(provider)}>{this.ui('common.delete')}</button>
              </div>
            </div>
          ))}
        </div>
      </div>
    );
  }

  renderAgentManager() {
    return (
      <div className="ZaiBotSettingsPanel-subsection">
        <div className="ZaiBotSettingsPanel-subsectionHeader">
          <h4>{this.ui('agent.section_title')}</h4>
          <button type="button" className="Button Button--text" onclick={() => (this.agentForm = this.emptyAgentForm())}>
            {this.ui('common.new')}
          </button>
        </div>

        <div className="ZaiBotSettingsPanel-grid">
          <div className="Form-group">
            <label>{this.ui('agent.name')}</label>
            <input className="FormControl" value={this.agentForm.name} oninput={(e) => (this.agentForm.name = e.target.value)} />
          </div>
          <div className="Form-group">
            <label>{this.ui('agent.provider')}</label>
            <select className="FormControl" value={this.agentForm.providerId} onchange={(e) => (this.agentForm.providerId = e.target.value)}>
              <option value="">{this.ui('common.default')}</option>
              {this.providers.map((provider) => (
                <option key={provider.id} value={provider.id}>{provider.name}</option>
              ))}
            </select>
          </div>
          <div className="Form-group">
            <label>{this.ui('agent.username')}</label>
            <input className="FormControl" value={this.agentForm.username} oninput={(e) => (this.agentForm.username = e.target.value)} />
          </div>
          <div className="Form-group">
            <label>{this.ui('agent.email')}</label>
            <input className="FormControl" value={this.agentForm.email} oninput={(e) => (this.agentForm.email = e.target.value)} />
          </div>
          <div className="Form-group ZaiBotSettingsPanel-gridSpan2">
            <label>{this.ui('agent.personality')}</label>
            <textarea className="FormControl" rows="3" value={this.agentForm.personality} oninput={(e) => (this.agentForm.personality = e.target.value)} />
          </div>
          <div className="Form-group">
            <label>{this.ui('agent.expertise')}</label>
            <input className="FormControl" value={this.agentForm.expertise} oninput={(e) => (this.agentForm.expertise = e.target.value)} />
          </div>
          <div className="Form-group">
            <label>{this.ui('agent.reply_mode')}</label>
            <select className="FormControl" value={this.agentForm.replyMode} onchange={(e) => (this.agentForm.replyMode = e.target.value)}>
              <option value="mention">{this.ui('agent.reply_mode_mention')}</option>
              <option value="all">{this.ui('agent.reply_mode_all')}</option>
            </select>
          </div>
          <div className="Form-group">
            <label>{this.ui('agent.chat_model')}</label>
            <input className="FormControl" value={this.agentForm.chatModel} oninput={(e) => (this.agentForm.chatModel = e.target.value)} />
          </div>
          <div className="Form-group">
            <label>{this.ui('agent.embedding_model')}</label>
            <input className="FormControl" value={this.agentForm.embeddingModel} oninput={(e) => (this.agentForm.embeddingModel = e.target.value)} />
          </div>
          <div className="Form-group">
            <label>{this.ui('agent.temperature')}</label>
            <input className="FormControl" type="number" min="0" max="1" step="0.1" value={this.agentForm.temperature} oninput={(e) => (this.agentForm.temperature = e.target.value)} />
          </div>
          <div className="Form-group">
            <label>{this.ui('agent.language')}</label>
            <input className="FormControl" value={this.agentForm.language} oninput={(e) => (this.agentForm.language = e.target.value)} />
          </div>
          <div className="Form-group ZaiBotSettingsPanel-gridSpan2">
            <label>{this.ui('agent.system_prompt')}</label>
            <textarea className="FormControl" rows="4" value={this.agentForm.systemPrompt} oninput={(e) => (this.agentForm.systemPrompt = e.target.value)} />
          </div>
          <div className="Form-group ZaiBotSettingsPanel-gridSpan2">
            <label>{this.ui('agent.active_tags')}</label>
            <textarea className="FormControl" rows="4" value={this.agentForm.activeTagsText} oninput={(e) => (this.agentForm.activeTagsText = e.target.value)} />
          </div>
          <div className="Form-group">
            <label className="checkbox">
              <input type="checkbox" checked={this.agentForm.isActive} onchange={(e) => (this.agentForm.isActive = e.target.checked)} />
              {this.ui('common.active')}
            </label>
          </div>
        </div>

        <div className="Form-group">
          <button type="button" className="Button Button--primary" disabled={this.agentSaving} onclick={() => this.saveAgent()}>
            {this.agentSaving ? this.ui('common.saving') : this.agentForm.id ? this.ui('agent.update') : this.ui('agent.create')}
          </button>
        </div>

        <div className="ZaiBotSettingsPanel-list">
          {this.agents.map((agent) => (
            <div key={agent.id} className="ZaiBotSettingsPanel-listItem">
              <div>
                <strong>{agent?.name || this.ui('agent.unnamed')}</strong>
                <div className="helpText">
                  {agent?.user?.username || this.ui('agent.no_user')} · {agent?.replyMode === 'all' ? this.ui('agent.reply_mode_all') : this.ui('agent.reply_mode_mention')} · {(agent?.provider?.name) || this.ui('agent.default_provider')}
                </div>
              </div>
              <div className="ZaiBotSettingsPanel-actions">
                <button type="button" className="Button Button--text" onclick={() => this.editAgent(agent)}>{this.ui('common.edit')}</button>
                <button type="button" className="Button Button--text" onclick={() => this.deleteAgent(agent)}>{this.ui('common.delete')}</button>
              </div>
            </div>
          ))}
        </div>
      </div>
    );
  }

  renderTestChat() {
    return (
      <div className="ZaiBotSettingsPanel-subsection">
        <div className="ZaiBotSettingsPanel-subsectionHeader">
          <h4>{this.ui('test_chat.section_title')}</h4>
        </div>
        <div className="ZaiBotSettingsPanel-grid">
          <div className="Form-group">
            <label>{this.ui('test_chat.agent')}</label>
            <select className="FormControl" value={this.testChatForm.agentId} onchange={(e) => (this.testChatForm.agentId = e.target.value)}>
              <option value="">{this.ui('test_chat.select_agent')}</option>
              {this.agents.map((agent) => (
                <option key={agent.id} value={agent.id}>{agent.name}</option>
              ))}
            </select>
          </div>
          <div className="Form-group ZaiBotSettingsPanel-gridSpan2">
            <label>{this.ui('test_chat.message')}</label>
            <textarea className="FormControl" rows="4" value={this.testChatForm.message} oninput={(e) => (this.testChatForm.message = e.target.value)} />
          </div>
        </div>
        <div className="Form-group">
          <button type="button" className="Button Button--secondary" disabled={this.testingChat || !this.testChatForm.agentId || !this.testChatForm.message} onclick={() => this.testChat()}>
            {this.testingChat ? this.ui('common.testing') : this.ui('test_chat.run')}
          </button>
        </div>
        {this.testChatResult && (
          <div className="ZaiBotSettingsPanel-note">
            <pre>{JSON.stringify(this.testChatResult, null, 2)}</pre>
          </div>
        )}
      </div>
    );
  }

  renderMaintenance() {
    return (
      <div className="ZaiBotSettingsPanel-subsection">
        <div className="ZaiBotSettingsPanel-subsectionHeader">
          <h4>{this.ui('maintenance.section_title')}</h4>
        </div>
        <div className="ZaiBotSettingsPanel-actions">
          {[
            ['export', 'export'],
            ['decayMemories', 'decay_memories'],
            ['cleanupSessions', 'cleanup_sessions'],
            ['updatePersonalityTags', 'refresh_personality_tags'],
          ].map(([action, label]) => (
            <button
              key={action}
              type="button"
              className="Button Button--secondary"
              disabled={this.maintenanceLoading}
              onclick={() => this.runMaintenance(action)}
            >
              {this.ui(`maintenance.${label}`)}
            </button>
          ))}
        </div>
        {this.maintenanceResult && (
          <div className="ZaiBotSettingsPanel-note">
            <pre>{JSON.stringify(this.maintenanceResult, null, 2)}</pre>
          </div>
        )}
      </div>
    );
  }

  content() {
    const tabs = this.tabs();
    const currentTab = tabs.find((tab) => tab.key === this.activeTab) || tabs[0];

    return (
      <div className="ZaiBotSettingsPanel">
        <div className="ZaiBotSettingsPanel-header">
          <h2>{this.translator('page.title')}</h2>
          <p>{this.translator('page.description')}</p>
        </div>

        {this.renderDashboard()}
        {this.renderIntegrations()}

        {this.adminDataError && <div className="ZaiBotSettingsPanel-note ZaiBotSettingsPanel-status--error">{this.adminDataError}</div>}

        <div className="ZaiBotSettingsPanel-tabs">
          {tabs.map((tab) => (
            <button
              key={tab.key}
              type="button"
              className={`Button ${tab.key === currentTab.key ? 'Button--primary' : 'Button--text'}`}
              onclick={() => {
                this.activeTab = tab.key;
              }}
            >
              {this.translator(`tabs.${tab.key}.title`)}
            </button>
          ))}
        </div>

        <div className="ZaiBotSettingsPanel-section">
          <h3>{this.translator(`tabs.${currentTab.key}.title`)}</h3>
          <p className="helpText">{this.translator(`tabs.${currentTab.key}.description`)}</p>
          {currentTab.fields.map((field, index) => (
            <div key={field.key || `markup-${index}`}>{this.renderInput(field)}</div>
          ))}

          {currentTab.key === 'api' && this.renderProviderManager()}
          {currentTab.key === 'agents' && this.renderAgentManager()}
          {currentTab.key === 'agents' && this.renderTestChat()}
          {currentTab.key === 'debug' && this.renderMaintenance()}
        </div>
      </div>
    );
  }
}
