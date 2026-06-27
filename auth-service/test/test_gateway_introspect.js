const BASE_URL = 'http://localhost:3001'; 

async function testIntrospection() {
  console.log('='.repeat(60));
  console.log('Testing OAuth 2.0 Introspection Endpoint');
  console.log('='.repeat(60));

  console.log('\n📝 1. Register user...');
  try {
    const registerRes = await fetch(`${BASE_URL}/auth/register`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        name: 'Test User',
        email: 'test@introspect.com',
        password: 'password123',
        role: 'citizen'
      })
    });
    console.log(`   Status: ${registerRes.status}`);
    const registerData = await registerRes.json();
    console.log(`   Response:`, JSON.stringify(registerData, null, 2));
  } catch (error) {
    console.log(`   ❌ Register failed: ${error.message}`);
  }

  console.log('\n🔑 2. Login...');
  let accessToken = null;
  let refreshToken = null;
  
  try {
    const loginRes = await fetch(`${BASE_URL}/oauth/token`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        grant_type: 'password',
        email: 'test@introspect.com',
        password: 'password123'
      })
    });
    const loginData = await loginRes.json();
    console.log(`   Status: ${loginRes.status}`);
    
    if (loginData.success) {
      accessToken = loginData.access_token;
      refreshToken = loginData.refresh_token;
      console.log(`   ✅ Access Token: ${accessToken?.substring(0, 30)}...`);
      console.log(`   ✅ Refresh Token: ${refreshToken?.substring(0, 30)}...`);
    } else {
      console.log(`   ❌ Login failed: ${loginData.message}`);
      return;
    }
  } catch (error) {
    console.log(`   ❌ Login error: ${error.message}`);
    return;
  }

  console.log('\n🔍 3. Introspect Access Token...');
  try {
    const introspectRes = await fetch(`${BASE_URL}/oauth/introspect`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ token: accessToken })
    });
    const introspectData = await introspectRes.json();
    console.log(`   Status: ${introspectRes.status}`);
    console.log(`   Response:`, JSON.stringify(introspectData, null, 2));
  } catch (error) {
    console.log(`   ❌ Introspect failed: ${error.message}`);
  }


  console.log('\n🔄 4. Introspect Refresh Token...');
  if (refreshToken) {
    try {
      const introspectRes = await fetch(`${BASE_URL}/oauth/introspect`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ token: refreshToken })
      });
      const introspectData = await introspectRes.json();
      console.log(`   Status: ${introspectRes.status}`);
      console.log(`   Response:`, JSON.stringify(introspectData, null, 2));
    } catch (error) {
      console.log(`   ❌ Introspect refresh token failed: ${error.message}`);
    }
  }


  console.log('\n❌ 5. Introspect Invalid Token...');
  try {
    const invalidRes = await fetch(`${BASE_URL}/oauth/introspect`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ token: 'invalid.token.here' })
    });
    const invalidData = await invalidRes.json();
    console.log(`   Status: ${invalidRes.status}`);
    console.log(`   Response:`, JSON.stringify(invalidData, null, 2));
  } catch (error) {
    console.log(`   ❌ Test invalid token failed: ${error.message}`);
  }


  console.log('\n❌ 6. Introspect Without Token...');
  try {
    const noTokenRes = await fetch(`${BASE_URL}/oauth/introspect`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({})
    });
    const noTokenData = await noTokenRes.json();
    console.log(`   Status: ${noTokenRes.status}`);
    console.log(`   Response:`, JSON.stringify(noTokenData, null, 2));
  } catch (error) {
    console.log(`   ❌ Test no token failed: ${error.message}`);
  }

  console.log('\n' + '='.repeat(60));
  console.log('✅ Testing complete!');
  console.log('='.repeat(60));
}

testIntrospection().catch(console.error);