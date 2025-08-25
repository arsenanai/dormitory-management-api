# Academic Data Integration Plan

## Current State

The system currently uses **hardcoded faculty and specialty options** in the registration form:

### Faculty Options (Hardcoded)
- Engineering and natural sciences
- Business and economics  
- Law and social sciences

### Specialty Options (Hardcoded)
- Computer sciences
- Mechanical engineering
- Civil engineering

## Future Integration Approach

### Phase 1: External API Integration (When SDU Project is Ready)
1. **Create simple API endpoints** to fetch faculty/specialty data from external SDU system
2. **Replace hardcoded options** with dynamic data from external API
3. **Keep the same form structure** - just change data source

### Phase 2: Enhanced Features
1. **Real-time synchronization** with SDU system
2. **Faculty-specific specialty filtering** (when faculty is selected, show relevant specialties)
3. **Validation against external data** (ensure entered faculty/specialty exists in SDU system)

## Implementation Notes

- **No database changes needed** - faculty and specialty are stored as simple text fields in user registration
- **Form remains the same** - users manually type or select from dropdown options
- **Future integration** will be a simple data source swap, not a structural change
- **Backward compatibility** maintained - existing registrations continue to work

## Benefits of This Approach

1. **Simple and lightweight** - no complex database schema
2. **Easy to maintain** - minimal code changes needed
3. **Flexible** - can easily switch between hardcoded and external data
4. **Scalable** - can be sold to other universities with their own faculty structures
5. **Future-proof** - ready for external API integration when available

## Next Steps

1. **Keep current implementation** as-is
2. **When SDU API is ready**, create simple fetch endpoints
3. **Update frontend** to use external data instead of hardcoded options
4. **Add caching** for performance optimization
5. **Implement fallback** to hardcoded options if external API is unavailable
